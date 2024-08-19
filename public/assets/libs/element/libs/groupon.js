function flexColumnWidth(str, tableData, flag = "max") {
    str = str + "";
    let columnContent = "";
    if (
      !tableData ||
      !tableData.length ||
      tableData.length === 0 ||
      tableData === undefined
    ) {
      return 0;
    }
    if (!str || !str.length || str.length === 0 || str === undefined) {
      return 0;
    }
    if (flag === "equal") {
      for (let i = 0; i < tableData.length; i++) {
        if (tableData[i][str].length > 0) {
          columnContent = tableData[i][str];
          break;
        }
      }
    } else {
      let index = 0;
      for (let i = 0; i < tableData.length; i++) {
        if (tableData[i][str] === null) {
          return 0;
        }
        const now_temp = tableData[i][str] + "";
        const max_temp = tableData[index][str] + "";
        if (now_temp.length > max_temp.length) {
          index = i;
        }
      }
      columnContent = tableData[index][str];
    }
    let flexWidth = 0;
    for (const char of columnContent+'') {
      if ((char >= "A" && char <= "Z") || (char >= "a" && char <= "z")) {
        flexWidth += 8;
      } else if (char >= "\u4e00" && char <= "\u9fa5") {
        flexWidth += 15;
      } else {
        flexWidth += 10;
      }
    }
    if (flexWidth < 80) {
      flexWidth = 80;
    }
    return flexWidth;
  };

