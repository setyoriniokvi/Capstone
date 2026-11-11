import React from "react";
import { Easing, Img, interpolate, staticFile } from "remotion";
import cuts from "./cuts.json";
import { C, EASE_IO, EASE_OUT, FONT } from "./theme";

type Size = { w: number; h: number };
export const size = (name: string): Size => (cuts as Record<string, Size>)[name];

/* ---------------------------------------------------------------- easing */

export const BACK = Easing.out(Easing.back(1.6));
export const clamp01 = (v: number) => Math.max(0, Math.min(1, v));

/** 0→1 antara dua frame, dengan easing apa pun. */
export const ramp = (f: number, a: number, b: number, e: (t: number) => number = EASE_OUT) =>
  interpolate(f, [a, b], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: e });

/** Muncul dengan overshoot kecil (energi), nilai bisa > 1. */
export const pop = (f: number, at: number, dur = 16) => ramp(f, at, at + dur, BACK);

export const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

/* ------------------------------------------------------------ potongan UI */

export type Clip = { x: number; y: number; w: number; h: number };

/**
 * Potongan komponen UI asli (PNG transparan hasil tangkapan elemen DOM, DPR 3).
 * x,y = pojok kiri-atas setelah diskalakan; s = skala; clip = area sumber (piksel CSS) yang ditampilkan.
 * `children` memakai koordinat piksel CSS sumber (relatif ke area clip) sehingga cincin/sorot selalu tepat.
 */
export const Cut: React.FC<{
  name: string;
  x: number;
  y: number;
  s?: number;
  clip?: Clip;
  rot?: number;
  k?: number;
  tx?: number;
  ty?: number;
  opacity?: number;
  radius?: number;
  shadow?: boolean;
  z?: number;
  origin?: string;
  bg?: string;
  pad?: number;
  children?: React.ReactNode;
}> = ({ name, x, y, s = 1, clip, rot = 0, k = 1, tx = 0, ty = 0, opacity = 1, radius = 0, shadow = true, z, origin = "50% 50%", bg, pad = 0, children }) => {
  const sz = size(name);
  const c = clip ?? { x: 0, y: 0, w: sz.w, h: sz.h };
  if (opacity <= 0.001) return null;
  return (
    <div
      style={{
        position: "absolute",
        left: x,
        top: y,
        width: c.w * s,
        height: c.h * s,
        opacity,
        zIndex: z,
        transform: `translate(${tx}px, ${ty}px) rotate(${rot}deg) scale(${k})`,
        transformOrigin: origin,
        filter: shadow ? "drop-shadow(0 22px 38px rgba(0,0,0,0.26)) drop-shadow(0 3px 9px rgba(0,0,0,0.16))" : undefined,
      }}
    >
      {bg && <div style={{ position: "absolute", left: -pad * s, top: -pad * s, right: -pad * s, bottom: -pad * s, background: bg, borderRadius: (radius + pad) * s }} />}
      <div style={{ position: "absolute", inset: 0, overflow: "hidden", borderRadius: radius * s }}>
        <Img
          src={staticFile(`ui/${name}.png`)}
          style={{ position: "absolute", left: -c.x * s, top: -c.y * s, width: sz.w * s, height: sz.h * s, maxWidth: "none" }}
        />
      </div>
      <div style={{ position: "absolute", inset: 0, transformOrigin: "0 0" }}>
        <div style={{ position: "absolute", left: 0, top: 0, transform: `scale(${s})`, transformOrigin: "0 0", width: c.w, height: c.h }}>{children}</div>
      </div>
    </div>
  );
};

/** Sorot berbentuk kotak di dalam <Cut> (koordinat piksel CSS sumber). */
export const Hi: React.FC<{ r: Clip; p: number; color?: string; radius?: number; pad?: number; fill?: boolean; w?: number }> = ({
  r,
  p,
  color = C.accent,
  radius = 8,
  pad = 4,
  fill = false,
  w = 3,
}) => {
  if (p <= 0.001) return null;
  return (
    <div
      style={{
        position: "absolute",
        left: r.x - pad,
        top: r.y - pad,
        width: r.w + pad * 2,
        height: r.h + pad * 2,
        borderRadius: radius,
        border: `${w}px solid ${color}`,
        background: fill ? `${color}22` : undefined,
        boxShadow: `0 0 18px ${color}88`,
        opacity: clamp01(p),
        transform: `scale(${1 + (1 - clamp01(p)) * 0.1})`,
      }}
    />
  );
};

/* ---------------------------------------------------------------- kursor */

export type Way = { f: number; x: number; y: number };

export const Cursor: React.FC<{ f: number; way: Way[]; pressAt?: number; opacity?: number }> = ({ f, way, pressAt = -1, opacity = 1 }) => {
  const fs = way.map((w) => w.f);
  const ease = (t: number) => EASE_IO(t);
  const x = interpolate(f, fs, way.map((w) => w.x), { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: ease });
  const y = interpolate(f, fs, way.map((w) => w.y), { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: ease });
  const press = pressAt < 0 ? 0 : interpolate(f, [pressAt - 2, pressAt, pressAt + 5], [0, 1, 0], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  const o = opacity * ramp(f, fs[0], fs[0] + 6, (t) => t);
  return (
    <div style={{ position: "absolute", left: x, top: y, opacity: o, zIndex: 50, transform: `scale(${1 - press * 0.18})`, transformOrigin: "5px 3px", pointerEvents: "none" }}>
      <svg width="40" height="40" viewBox="0 0 24 24" style={{ transform: "translate(-5px,-3px)", filter: "drop-shadow(0 4px 6px rgba(0,0,0,0.35))" }}>
        <path d="M5 3l14 8-6 2 4 8-3 1.5-4-8-5 4z" fill="#fff" stroke="#0B1A12" strokeWidth="1.4" strokeLinejoin="round" />
      </svg>
    </div>
  );
};

/** Riak klik berwarna (ukuran layar tetap). */
export const Burst: React.FC<{ f: number; at: number; x: number; y: number; color?: string; max?: number }> = ({ f, at, x, y, color = C.accent, max = 130 }) => {
  const p = ramp(f, at, at + 18, (t) => 1 - Math.pow(1 - t, 3));
  if (f < at || p >= 1) return null;
  const d = 30 + p * max;
  return (
    <div
      style={{
        position: "absolute",
        left: x - d / 2,
        top: y - d / 2,
        width: d,
        height: d,
        borderRadius: "50%",
        border: `4px solid ${color}`,
        opacity: 1 - p,
        zIndex: 40,
      }}
    />
  );
};

export const Plane: React.FC<{ s?: number; color?: string }> = ({ s = 64, color = C.ink }) => (
  <svg width={s} height={s} viewBox="0 0 24 24">
    <path d="M2 21l21-9L2 3v7l15 2-15 2z" fill={color} />
  </svg>
);

/* -------------------------------------------------------------- tipografi */

/** Baris teks yang naik dari balik topeng, kata per kata. */
export const RiseLines: React.FC<{
  f: number;
  at: number;
  lines: { t: string; color?: string }[];
  size: number;
  x: number;
  y: number;
  weight?: number;
  line?: number;
  step?: number;
  letter?: string;
  align?: "left" | "center";
  width?: number;
}> = ({ f, at, lines, size: fs, x, y, weight = 800, line = 1.02, step = 6, letter = "-0.035em", align = "left", width }) => (
  <div style={{ position: "absolute", left: x, top: y, width, fontFamily: FONT, textAlign: align }}>
    {lines.map((l, i) => {
      const p = ramp(f, at + i * step, at + i * step + 20, EASE_OUT);
      return (
        <div key={i} style={{ overflow: "hidden", paddingBottom: fs * 0.12, marginBottom: -fs * 0.12 }}>
          <div
            style={{
              fontSize: fs,
              fontWeight: weight,
              lineHeight: line,
              letterSpacing: letter,
              color: l.color ?? C.ink,
              transform: `translateY(${(1 - p) * 110}%)`,
              opacity: clamp01(p * 2),
              whiteSpace: "nowrap",
            }}
          >
            {l.t}
          </div>
        </div>
      );
    })}
  </div>
);

export const Chip: React.FC<{ text: string; bg?: string; color?: string; f: number; at: number; x: number; y: number; size?: number }> = ({
  text,
  bg = C.ink,
  color = C.white,
  f,
  at,
  x,
  y,
  size: fs = 24,
}) => {
  const p = pop(f, at);
  return (
    <div
      style={{
        position: "absolute",
        left: x,
        top: y,
        padding: `${fs * 0.36}px ${fs * 0.9}px`,
        borderRadius: 999,
        background: bg,
        color,
        fontFamily: FONT,
        fontWeight: 700,
        fontSize: fs,
        letterSpacing: "0.16em",
        transform: `scale(${p})`,
        transformOrigin: "0 50%",
        opacity: clamp01(p * 2),
      }}
    >
      {text}
    </div>
  );
};

/** Kata raksasa samar di latar (gerak paralaks pelan). */
export const Giant: React.FC<{ f: number; text: string; x: number; y: number; color: string; size?: number; drift?: number }> = ({ text, f, x, y, color, size: fs = 560, drift = -0.8 }) => (
  <div
    style={{
      position: "absolute",
      left: x,
      top: y,
      fontFamily: FONT,
      fontSize: fs,
      fontWeight: 800,
      letterSpacing: "-0.06em",
      lineHeight: 0.8,
      color,
      whiteSpace: "nowrap",
      transform: `translateX(${f * drift}px)`,
      userSelect: "none",
    }}
  >
    {text}
  </div>
);

export const Bg: React.FC<{ color: string; glow?: string; gx?: number; gy?: number }> = ({ color, glow, gx = 1500, gy = 300 }) => (
  <div
    style={{
      position: "absolute",
      inset: 0,
      background: glow ? `radial-gradient(1100px 800px at ${gx}px ${gy}px, ${glow}, rgba(0,0,0,0) 70%), ${color}` : color,
    }}
  />
);

export const MImg: React.FC<{ name: string; opacity?: number; ty?: number }> = ({ name, opacity = 1, ty = 0 }) => (
  <Img
    src={staticFile(`ui/${name}.png`)}
    style={{ position: "absolute", left: 0, top: ty, width: 390, height: 844, opacity }}
  />
);
