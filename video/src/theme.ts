import { Easing, interpolate } from "remotion";

/** Warna: token produk (resources/css/tokens.css) + turunan hijau gelap untuk panggung sinematik. */
export const C = {
  stage: "#03241A",
  stageLift: "#07402A",
  brand: "#00843D",
  deep: "#006B32",
  accent: "#00B388",
  paper: "#F5F5F7",
  ink: "#1D1D1F",
  white: "#FFFFFF",
  danger: "#EF4444",
  softWhite: "rgba(255,255,255,0.78)",
  faintWhite: "rgba(255,255,255,0.55)",
};

export const FONT = "Inter, system-ui, sans-serif";

/** Easing yang sama dengan token gerak produk (--ease-out) — tanpa bounce. */
export const EASE_OUT = Easing.bezier(0.22, 1, 0.36, 1);
export const EASE_IO = Easing.bezier(0.65, 0, 0.35, 1);
export const EASE_CAM = Easing.bezier(0.45, 0, 0.15, 1);

export const tween = (
  frame: number,
  from: number,
  to: number,
  a: number,
  b: number,
  easing: (t: number) => number = EASE_OUT,
) =>
  interpolate(frame, [from, to], [a, b], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing,
  });

/** Rentang aktif 0→1→0 : naik di [a,b], turun di [c,d]. */
export const pulse = (frame: number, a: number, b: number, c: number, d: number) =>
  interpolate(frame, [a, b, c, d], [0, 1, 1, 0], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });
