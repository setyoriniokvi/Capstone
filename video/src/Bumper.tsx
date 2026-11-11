import React, { useEffect, useState } from "react";
import { AbsoluteFill, Img, Sequence, cancelRender, continueRender, delayRender, interpolate, random, staticFile, useCurrentFrame } from "remotion";
import { Audio } from "@remotion/media";
import timeline from "./timeline.json";
import { fontsReady } from "./fonts";
import { C, EASE_IO, FONT } from "./theme";
import { BG, S1, S2, S3, S4, S5, S6, S7, S8, WIPE } from "./story";
import { BACK, clamp01, ramp } from "./kit";

const S = timeline.scenes;
const len = (k: keyof typeof S) => S[k][1] - S[k][0];

/* ----------------------------------------------------------- gelombang */

/** Lingkaran berwarna latar adegan berikutnya yang membesar dari titik klik, lalu adegan berganti. */
const WIPES: { end: number; x: number; y: number; color: string }[] = [
  { end: S.s2[0], ...WIPE.w1, color: BG.s2 },
  { end: S.s3[0], ...WIPE.w2, color: BG.s3 },
  { end: S.s4[0], ...WIPE.w3, color: BG.s4 },
  { end: S.s5[0], ...WIPE.w4, color: BG.s5 },
  { end: S.s6[0], ...WIPE.w5, color: BG.s6 },
  { end: S.s7[0], ...WIPE.w6, color: BG.s7 },
  { end: S.s8[0], ...WIPE.w7, color: BG.s8 },
];
const WIPE_LEN = 14;

const Wipes: React.FC = () => {
  const f = useCurrentFrame();
  return (
    <>
      {WIPES.map((w, i) => {
        const a = w.end - WIPE_LEN;
        if (f < a || f >= w.end) return null;
        const t = ramp(f, a, w.end, (x) => x * x * (3 - 2 * x));
        const r = 2300 * t;
        return <div key={i} style={{ position: "absolute", left: w.x - r, top: w.y - r, width: r * 2, height: r * 2, borderRadius: "50%", background: w.color }} />;
      })}
    </>
  );
};

/* ---------------------------------------------------------------- dock */

const ribbonW = (f: number) => {
  const k = timeline.ribbon;
  return interpolate(f, k.map((x) => x.f), k.map((x) => x.w), { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: EASE_IO });
};
const ROLES: [number, number, string][] = [
  [S.s2[0], S.s2[1], "Admin"],
  [S.s3[0], S.s3[1], "Mahasiswa"],
  [S.s4[0], S.s5[1], "Dosen"],
  [S.s6[0], S.s6[1], "Admin → Dosen"],
  [S.s7[0], S.s7[1], "Admin"],
];

const DOCK = { x: 320, y: 972, w: 1280, h: 72 };
const NX = (w: number) => DOCK.x + 262 + (w - 1) * 50;

const Dock: React.FC = () => {
  const f = useCurrentFrame();
  const inP = ramp(f, 56, 76, BACK) * (1 - ramp(f, S.s8[0] + 48, S.s8[0] + 62, (t) => t));
  const w = ribbonW(f);
  const wi = Math.round(w);
  const cx = NX(w);
  const role = ROLES.find((r) => f >= r[0] && f < r[1]);
  const rp = role ? ramp(f, role[0] + 4, role[0] + 18) * (1 - ramp(f, role[1] - 6, role[1], (t) => t)) : 0;
  if (inP <= 0.001) return null;
  return (
    <div style={{ position: "absolute", left: DOCK.x, top: DOCK.y, width: DOCK.w, height: DOCK.h, transform: `translateY(${(1 - clamp01(inP)) * 120}px)`, opacity: clamp01(inP * 2), fontFamily: FONT, zIndex: 60 }}>
      <div style={{ position: "absolute", inset: 0, borderRadius: 36, background: "rgba(4,22,15,0.94)", border: "1.5px solid rgba(255,255,255,0.14)", boxShadow: "0 24px 60px rgba(0,0,0,0.4)" }} />
      <div style={{ position: "absolute", left: 34, top: 0, height: DOCK.h, display: "flex", alignItems: "baseline", gap: 12, paddingTop: 16 }}>
        <span style={{ fontSize: 15, fontWeight: 700, letterSpacing: "0.22em", color: C.faintWhite }}>MINGGU</span>
        <span style={{ fontSize: 34, fontWeight: 800, letterSpacing: "-0.03em", color: C.white, fontVariantNumeric: "tabular-nums" }}>{String(wi).padStart(2, "0")}</span>
      </div>
      <div style={{ position: "absolute", left: 262, top: DOCK.h / 2 - 2, width: 750, height: 4, borderRadius: 2, background: "rgba(255,255,255,0.18)" }} />
      <div style={{ position: "absolute", left: 262, top: DOCK.h / 2 - 2, width: cx - NX(1), height: 4, borderRadius: 2, background: C.accent }} />
      {Array.from({ length: 16 }, (_, i) => {
        const x = 262 + i * 50;
        const done = DOCK.x + x < cx - 1;
        if (Math.abs(DOCK.x + x - cx) < 1) return null;
        return <div key={i} style={{ position: "absolute", left: x - 7, top: DOCK.h / 2 - 7, width: 14, height: 14, borderRadius: "50%", boxSizing: "border-box", background: done ? C.accent : "#0A241A", border: `2.5px solid ${done ? C.accent : "rgba(255,255,255,0.35)"}` }} />;
      })}
      <div style={{ position: "absolute", left: cx - DOCK.x - 22, top: DOCK.h / 2 - 22, width: 44, height: 44, borderRadius: "50%", background: C.white, boxShadow: `0 0 0 4px ${C.accent}, 0 0 24px rgba(0,179,136,0.7)`, display: "flex", alignItems: "center", justifyContent: "center" }}>
        <Img src={staticFile("brand/telu-mark.png")} style={{ height: 27 }} />
      </div>
      <div style={{ position: "absolute", right: 30, top: 0, height: DOCK.h, display: "flex", alignItems: "center", fontSize: 21, fontWeight: 700, color: C.white, opacity: rp, transform: `translateY(${(1 - rp) * 8}px)` }}>{role ? role[2] : ""}</div>
    </div>
  );
};

const Grain: React.FC = () => {
  const f = useCurrentFrame();
  const seed = Math.floor(f / 2);
  const dots = Array.from({ length: 60 }, (_, i) => ({ x: random(`x${seed}${i}`) * 1920, y: random(`y${seed}${i}`) * 1080 }));
  return (
    <svg width={1920} height={1080} style={{ position: "absolute", inset: 0, opacity: 0.05, mixBlendMode: "multiply" }}>
      {dots.map((d, i) => (
        <circle key={i} cx={d.x} cy={d.y} r={1.2} fill="#000" />
      ))}
    </svg>
  );
};

export const Bumper: React.FC = () => {
  const [handle] = useState(() => delayRender("Memuat font"));
  useEffect(() => {
    fontsReady.then(() => continueRender(handle)).catch((e) => cancelRender(e));
  }, [handle]);

  const seq = (k: keyof typeof S, node: React.ReactNode) => (
    <Sequence from={S[k][0]} durationInFrames={len(k)} premountFor={30}>
      {node}
    </Sequence>
  );
  return (
    <AbsoluteFill style={{ background: C.stage, fontFamily: FONT }}>
      <Audio src={staticFile("audio/bumper.wav")} />
      {seq("s1", <S1 />)}
      {seq("s2", <S2 />)}
      {seq("s3", <S3 />)}
      {seq("s4", <S4 />)}
      {seq("s5", <S5 />)}
      {seq("s6", <S6 />)}
      {seq("s7", <S7 />)}
      {seq("s8", <S8 />)}
      <Wipes />
      <Dock />
      <Grain />
    </AbsoluteFill>
  );
};
