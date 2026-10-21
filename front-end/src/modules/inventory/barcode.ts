const L = [
  "0001101",
  "0011001",
  "0010011",
  "0111101",
  "0100011",
  "0110001",
  "0101111",
  "0111011",
  "0110111",
  "0001011",
];

const G = [
  "0100111",
  "0110011",
  "0011011",
  "0100001",
  "0011101",
  "0111001",
  "0000101",
  "0010001",
  "0001001",
  "0010111",
];

const R = [
  "1110010",
  "1100110",
  "1101100",
  "1000010",
  "1011100",
  "1001110",
  "1010000",
  "1000100",
  "1001000",
  "1110100",
];

const PARITY = [
  "LLLLLL",
  "LLGLGG",
  "LLGGLG",
  "LLGGGL",
  "LGLLGG",
  "LGGLLG",
  "LGGGLL",
  "LGLGLG",
  "LGLGGL",
  "LGGLGL",
];

/** Code 128 symbol widths. The last entry is the stop symbol. */
const CODE128 = [
  "212222", "222122", "222221", "121223", "121322", "131222", "122213", "122312", "132212", "221213",
  "221312", "231212", "112232", "122132", "122231", "113222", "123122", "123221", "223211", "221132",
  "221231", "213212", "223112", "312131", "311222", "321122", "321221", "312212", "322112", "322211",
  "212123", "212321", "232121", "111323", "131123", "131321", "112313", "132113", "132311", "211313",
  "231113", "231311", "112133", "112331", "132131", "113123", "113321", "133121", "313121", "211331",
  "231131", "213113", "213311", "213131", "311123", "311321", "331121", "312113", "312311", "332111",
  "314111", "221411", "431111", "111224", "111422", "121124", "121421", "141122", "141221", "112214",
  "112412", "122114", "122411", "142112", "142211", "241211", "221114", "413111", "241112", "134111",
  "111242", "121142", "121241", "114212", "124112", "124211", "411212", "421112", "421211", "212141",
  "214121", "412121", "111143", "111341", "131141", "114113", "114311", "411113", "411311", "113141",
  "114131", "311141", "411131", "211412", "211214", "211232", "2331112",
];

export function isEan13(code: string): boolean {
  if (!/^\d{13}$/.test(code)) return false;
  let sum = 0;
  for (let index = 0; index < 12; index++) {
    const digit = Number(code[index]);
    sum += index % 2 === 0 ? digit : digit * 3;
  }
  return String((10 - (sum % 10)) % 10) === code[12];
}

export function barcodeSvg(code: string, barHeight = 70): string | null {
  const value = code.trim();
  if (isEan13(value)) return ean13Svg(value, barHeight);
  if (/^[\x20-\x7e]+$/.test(value)) return code128Svg(value, barHeight);
  return null;
}

function ean13Svg(code: string, barHeight: number): string {
  const parity = PARITY[Number(code[0])] ?? "LLLLLL";
  let bits = "101";
  for (let index = 0; index < 6; index++) {
    const digit = Number(code[index + 1]);
    bits += parity[index] === "G" ? G[digit] : L[digit];
  }
  bits += "01010";
  for (let index = 0; index < 6; index++) {
    bits += R[Number(code[index + 7])];
  }
  bits += "101";
  return barsSvg(bits, 11, 7, barHeight);
}

function code128Svg(code: string, barHeight: number): string {
  const values = [104];
  for (const char of code) values.push(char.charCodeAt(0) - 32);
  let checksum = values[0] ?? 0;
  for (let index = 1; index < values.length; index++) {
    checksum += (values[index] ?? 0) * index;
  }
  values.push(checksum % 103);
  const widths = values.map((value) => CODE128[value] ?? "").join("") + (CODE128[106] ?? "");
  let bits = "";
  let bar = true;
  for (const width of widths) {
    bits += (bar ? "1" : "0").repeat(Number(width));
    bar = !bar;
  }
  return barsSvg(bits, 10, 10, barHeight);
}

function barsSvg(bits: string, quietLeft: number, quietRight: number, barHeight: number): string {
  const rects: string[] = [];
  let index = 0;
  while (index < bits.length) {
    if (bits[index] !== "1") {
      index++;
      continue;
    }
    const start = index;
    while (index < bits.length && bits[index] === "1") index++;
    rects.push(
      `<rect x="${quietLeft + start}" y="0" width="${index - start}" height="${barHeight}" fill="#000"/>`,
    );
  }
  const width = quietLeft + bits.length + quietRight;
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${barHeight}" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" shape-rendering="crispEdges" role="img" aria-label="barcode">${rects.join("")}</svg>`;
}
