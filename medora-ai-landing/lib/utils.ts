export function cn(...classes: Array<string | false | null | undefined>) {
  return classes.filter(Boolean).join(" ");
}

const FA_DIGITS = "۰۱۲۳۴۵۶۷۸۹";

/** Convert Latin digits (and the decimal point) to Persian numerals. */
export function toFaDigits(input: string | number): string {
  return String(input)
    .replace(/\d/g, (d) => FA_DIGITS[Number(d)])
    .replace(/\./g, "٫");
}
