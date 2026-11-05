import React, { createContext, useContext } from "react";
import { Img, interpolate, staticFile, useCurrentFrame } from "remotion";
import { C, EASE_CAM, EASE_OUT, FONT, tween } from "./theme";

/* ------------------------------------------------------------------ Kamera */

export type View = { x: number; y: number; w: number };
export type CamKey = { f: number; v: View };

/** Interpolasi kamera antar keyframe (koordinat piksel CSS tangkapan layar). */
export const camera = (frame: number, keys: CamKey[]): View => {
  const fs = keys.map((k) => k.f);
  const ease = (t: number) => EASE_CAM(t);
  const get = (p: keyof View) =>
    interpolate(frame, fs, keys.map((k) => k.v[p]), {
      extrapolateLeft: "clamp",
      extrapolateRight: "clamp",
      easing: ease,
    });
  return { x: get("x"), y: get("y"), w: get("w") };
};

const ScaleCtx = createContext(1);
export const useCamScale = () => useContext(ScaleCtx);

/**
 * Lapisan kamera: anak-anaknya memakai koordinat piksel CSS tangkapan (1920×1080),
 * lalu seluruhnya digeser & diskalakan agar `view` memenuhi lebar kartu.
 */
export const CamLayer: React.FC<{
  view: View;
  cardW: number;
  opacity?: number;
  extra?: React.CSSProperties;
  children: React.ReactNode;
}> = ({ view, cardW, opacity = 1, extra, children }) => {
  const s = cardW / view.w;
  return (
    <ScaleCtx.Provider value={s}>
      <div
        style={{
          position: "absolute",
          left: 0,
          top: 0,
          width: 1920,
          height: 1080,
          transformOrigin: "0 0",
          translate: `${-view.x * s}px ${-view.y * s}px`,
          scale: s,
          opacity,
          ...extra,
        }}
      >
        {children}
      </div>
    </ScaleCtx.Provider>
  );
};

/** Tangkapan layar UI asli (3840×2160, ditampilkan 1920×1080 CSS). */
export const Shot: React.FC<{ name: string; opacity?: number }> = ({ name, opacity = 1 }) => (
  <Img
    src={staticFile(`captures/${name}.png`)}
    style={{ position: "absolute", left: 0, top: 0, width: 1920, height: 1080, opacity }}
  />
);

/* ----------------------------------------------------------------- Kartu UI */

export type Rect = { x: number; y: number; w: number; h: number };

export const Card: React.FC<{
  rect: Rect;
  opacity?: number;
  tx?: number;
  ty?: number;
  scale?: number;
  children: React.ReactNode;
}> = ({ rect, opacity = 1, tx = 0, ty = 0, scale = 1, children }) => (
  <div
    style={{
      position: "absolute",
      left: rect.x,
      top: rect.y,
      width: rect.w,
      height: rect.h,
      borderRadius: 22,
      overflow: "hidden",
      background: C.white,
      opacity,
      translate: `${tx}px ${ty}px`,
      scale,
      boxShadow:
        "0 44px 110px rgba(0,0,0,0.5), 0 14px 34px rgba(0,0,0,0.28), 0 0 0 1px rgba(255,255,255,0.10)",
    }}
  >
    {children}
  </div>
);

/** Masuk & keluar kartu per adegan (frame lokal). */
export const useSceneMotion = (duration: number, enterAt = 0, exitLen = 8) => {
  const frame = useCurrentFrame();
  const inP = tween(frame, enterAt, enterAt + 16, 0, 1, EASE_OUT);
  const outP = tween(frame, duration - exitLen, duration, 0, 1, (t) => t * t);
  return {
    frame,
    opacity: inP * (1 - outP),
    tx: (1 - inP) * 90 - outP * 40,
    scale: 0.985 + 0.015 * inP,
  };
};

/* ------------------------------------------------------------- Anotasi UI */

/** Penutup berwarna latar panel — untuk membuka elemen asli secara bertahap. */
export const Cover: React.FC<{ r: Rect; opacity: number; color?: string }> = ({ r, opacity, color = C.white }) => (
  <div
    style={{
      position: "absolute",
      left: r.x,
      top: r.y,
      width: r.w,
      height: r.h,
      background: color,
      opacity,
    }}
  />
);

/** Bingkai sorot (tebal garis tetap di layar, bukan ikut zoom kamera). */
export const Ring: React.FC<{ r: Rect; p: number; color?: string; radius?: number; pad?: number }> = ({
  r,
  p,
  color = C.accent,
  radius = 10,
  pad = 6,
}) => {
  const s = useCamScale();
  if (p <= 0.001) return null;
  const grow = 1 + (1 - p) * 0.08;
  return (
    <div
      style={{
        position: "absolute",
        left: r.x - pad,
        top: r.y - pad,
        width: r.w + pad * 2,
        height: r.h + pad * 2,
        borderRadius: radius,
        border: `${3.5 / s}px solid ${color}`,
        boxShadow: `0 0 ${18 / s}px ${color}`,
        opacity: p,
        scale: grow,
      }}
    />
  );
};

/** Riak klik. */
export const Ripple: React.FC<{ cx: number; cy: number; frame: number; at: number; color?: string }> = ({
  cx,
  cy,
  frame,
  at,
  color = C.accent,
}) => {
  const s = useCamScale();
  const p = tween(frame, at, at + 16, 0, 1);
  if (frame < at || p >= 1) return null;
  const size = (30 + p * 70) / s;
  return (
    <div
      style={{
        position: "absolute",
        left: cx - size / 2,
        top: cy - size / 2,
        width: size,
        height: size,
        borderRadius: "50%",
        border: `${3 / s}px solid ${color}`,
        opacity: 1 - p,
      }}
    />
  );
};

/* -------------------------------------------------------------------- Teks */

export const Headline: React.FC<{
  lines: string[];
  sub?: string;
  subAt?: number;
  frame: number;
  at?: number;
  outAt: number;
  accentLast?: boolean;
}> = ({ lines, sub, subAt = 22, frame, at = 8, outAt, accentLast }) => {
  const out = tween(frame, outAt, outAt + 8, 0, 1, (t) => t);
  return (
    <div
      style={{
        position: "absolute",
        left: 96,
        top: 330,
        width: 520,
        fontFamily: FONT,
        opacity: 1 - out,
      }}
    >
      {lines.map((l, i) => {
        const p = tween(frame, at + i * 6, at + i * 6 + 18, 0, 1);
        return (
          <div
            key={l}
            style={{
              fontSize: 62,
              lineHeight: 1.06,
              fontWeight: 700,
              letterSpacing: "-0.025em",
              color: accentLast && i === lines.length - 1 ? C.accent : C.white,
              opacity: p,
              translate: `0 ${(1 - p) * 28}px`,
            }}
          >
            {l}
          </div>
        );
      })}
      {sub ? (
        <div
          style={{
            marginTop: 26,
            fontSize: 28,
            lineHeight: 1.35,
            fontWeight: 400,
            color: C.softWhite,
            opacity: tween(frame, subAt, subAt + 18, 0, 1),
            translate: `0 ${(1 - tween(frame, subAt, subAt + 18, 0, 1)) * 18}px`,
          }}
        >
          {sub}
        </div>
      ) : null}
    </div>
  );
};
