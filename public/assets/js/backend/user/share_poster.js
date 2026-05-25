define(['jquery', 'bootstrap', 'backend', 'form'], function ($, undefined, Backend, Form) {

    var Controller = {
        index: function () {
            var refreshPreview = function () {
                var value = $.trim($("#c-share_image").val() || '');
                var preview = $("#share-poster-preview");
                if (!value) {
                    preview.html('暂无海报图');
                    return;
                }
                var src = value;
                if (src.indexOf('http://') !== 0 && src.indexOf('https://') !== 0) {
                    src = Fast.api.cdnurl(src, true);
                }
                preview.html('<img src="' + src + '" alt="分享海报图">');
            };

            $("#c-share_image").on('change input', refreshPreview);
            Form.api.bindevent($("form[role=form]"), function () {
                Toastr.success('分享海报图已保存');
            });
        }
    };
    return Controller;
});
