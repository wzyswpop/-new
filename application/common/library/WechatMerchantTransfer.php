<?php

namespace app\common\library;

use think\Log;

class WechatMerchantTransfer
{
    protected $base = 'https://api.mch.weixin.qq.com';
    protected $config = [];

    public function __construct(array $config)
    {
        foreach (['appid', 'mch_id', 'cert_public', 'cert_private'] as $key) {
            if (empty($config[$key])) {
                throw new \Exception('微信转账配置缺失：' . $key);
            }
        }
        if (empty($config['cert_serial'])) {
            $cert = $this->readCert($config['cert_public']);
            $parsed = openssl_x509_parse($cert, true);
            $config['cert_serial'] = isset($parsed['serialNumberHex']) ? $parsed['serialNumberHex'] : '';
        }
        if (empty($config['cert_serial'])) {
            throw new \Exception('商户证书序列号解析失败');
        }
        $config['cert_private'] = $this->readCert($config['cert_private']);
        $this->config = $config;
    }

    public static function instance(array $config)
    {
        return new static($config);
    }

    public function createBill(array $body)
    {
        if (empty($body['appid'])) {
            $body['appid'] = $this->config['appid'];
        }
        return $this->request('POST', '/v3/fund-app/mch-transfer/transfer-bills', $body);
    }

    public function queryByOutBillNo($outBillNo)
    {
        $path = '/v3/fund-app/mch-transfer/transfer-bills/out-bill-no/' . rawurlencode($outBillNo);
        return $this->request('GET', $path);
    }

    protected function request($method, $path, array $body = [])
    {
        $json = $body ? json_encode($body, JSON_UNESCAPED_UNICODE) : '';
        $timestamp = time();
        $nonce = uniqid() . mt_rand(1000, 9999);
        $message = $method . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $json . "\n";
        $token = sprintf(
            'mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $this->config['mch_id'],
            $nonce,
            $timestamp,
            $this->config['cert_serial'],
            $this->sign($message)
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->base . $path);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: hangcuicoffee',
            'Authorization: WECHATPAY2-SHA256-RSA2048 ' . $token,
        ]);
        if ($json !== '') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        }
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        $raw = curl_exec($curl);
        if ($raw === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \Exception('微信转账请求失败：' . $error);
        }
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $content = substr($raw, $headerSize);
        curl_close($curl);

        $result = json_decode($content, true);
        if (!is_array($result)) {
            Log::write('商家转账返回非JSON：' . $content);
            throw new \Exception('微信转账返回解析失败');
        }
        Log::write('商家转账返回：' . json_encode($result, JSON_UNESCAPED_UNICODE));
        if ($status < 200 || $status >= 300 || isset($result['code'])) {
            $message = isset($result['message']) ? $result['message'] : '微信转账受理失败';
            if (isset($result['code'])) {
                $message = $result['code'] . '：' . $message;
            }
            throw new \Exception($message);
        }
        return $result;
    }

    protected function sign($message)
    {
        $privateKey = openssl_pkey_get_private($this->config['cert_private']);
        if (!$privateKey) {
            throw new \Exception('商户私钥读取失败');
        }
        openssl_sign($message, $signature, $privateKey, 'sha256WithRSAEncryption');
        return base64_encode($signature);
    }

    protected function readCert($value)
    {
        if (strpos($value, '-----BEGIN') !== false) {
            return $value;
        }
        if (is_file($value)) {
            return file_get_contents($value);
        }
        throw new \Exception('证书文件不存在：' . $value);
    }
}
