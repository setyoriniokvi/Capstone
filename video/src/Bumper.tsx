import React, { useEffect, useState } from "react";
import { AbsoluteFill, Img, Sequence, cancelRender, continueRender, delayRender, interpolate, random, staticFile, useCurrentFrame } from "remotion";
import { Audio } from "@remotion/media";
import timeline from "./timeline.json";
import { fontsReady } from "./fonts";
import { C, EASE_IO, FONT, tween } from "./theme";
import { Admin, Bimbingan, Hook, Outro, Qr, Rekap, Rubrik, Student } from "./scenes";

const S = timeline.scenes;
const len = (k: keyof typeof S) => S[k][1] - S[k][0];

const NODE_Y = 1000;
const nodeX = (w: number) => 150 + (w - 1) * 108;
const ribbonW = (f: number) => {
  const k = timeline.ribbon;
  return interpolate(f, k.map((x) => x.f), k.map((x) => x.w), {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: EASE_IO,
  });
};

const ROLES: [number, number, string][] = [
  [S.admin[0], S.admin[1], "Admin"],
  [S.mhs[0], S.mhs[1], "Mahasiswa"],
  [S.bimbingan[0], S.rubrik[1], "Dosen"],
  [S.qr[0], S.qr[1], "Mahasiswa · Dosen"],
  [S.rekap[0], S.rekap[1], "Admin"],
];

const Stage: React.FC = () => {
  const f = useCurrentFrame();
  const drift = Math.sin(f / 90) * 40;
  return (
    <AbsoluteFill style={{ background: C.stage }}>
      <div
        style={{
          position: "absolute",
          inset: 0,
          background: `radial-gradient(900px 700px at ${1350 + drift}px 420px, ${C.stageLift} 0%, rgba(7,64,42,0) 70%), radial-gradient(800px 600px at ${300 - drift}px 900px, rgba(0,132,61,0.25) 0%, rgba(0,132,61,0) 70%)`,
        }}
      />
      <div
        style={{
          position: "absolute",
          inset: 0,
          background: "radial-gradient(ellipse at center, rgba(0,0,0,0) 55%, rgba(0,0,0,0.45) 100%)",
        }}
      />
    </AbsoluteFill>
  );
};

const Grain: React.FC = () => {
  const f = useCurrentFrame();
  const seed = Math.floor(f / 2);
  const dots = Array.from({ length: 70 }, (_, i) => ({
    x: random(`x${seed}${i}`) * 1920,
    y: random(`y${seed}${i}`) * 1080,
  }));
  return (
    <svg width={1920} height={1080} style={{ position: "absolute", inset: 0, opacity: 0.05 }}>
      {dots.map((d, i) => (
        <circle key={i} cx={d.x} cy={d.y} r={1.2} fill="#fff" />
      ))}
    </svg>
  );
};

const Hud: React.FC = () => {
  const f = useCurrentFrame();
  const w = Math.round(ribbonW(f));
  const role = ROLES.find((r) => f >= r[0] && f < r[1]);
  const a = tween(f, 112, 130, 0, 1) * tween(f, 838, 848, 1, 0, (t) => t);
  const rp = role ? tween(f, role[0] + 2, role[0] + 16, 0, 1) * tween(f, role[1] - 8, role[1], 1, 0, (t) => t) : 0;
  return (
    <div style={{ position: "absolute", left: 96, top: 84, fontFamily: FONT, opacity: a }}>
      <div style={{ fontSize: 20, fontWeight: 600, letterSpacing: "0.22em", color: C.faintWhite }}>MINGGU</div>
      <div style={{ fontSize: 76, fontWeight: 700, letterSpacing: "-0.03em", color: C.white, lineHeight: 1 }}>
        {String(w).padStart(2, "0")}
        <span style={{ fontSize: 30, fontWeight: 500, color: C.faintWhite, marginLeft: 10 }}>/ 16</span>
      </div>
      <div
        style={{
          marginTop: 16,
          display: "inline-block",
          padding: "8px 18px",
          borderRadius: 999,
          fontSize: 22,
          fontWeight: 600,
          color: C.white,
          background: "rgba(0,179,136,0.22)",
          border: "1.5px solid rgba(0,179,136,0.7)",
          opacity: rp,
        }}
      >
        {role ? role[2] : ""}
      </div>
    </div>
  );
};

const Mark: React.FC<{ d: number }> = ({ d }) => (
  <div
    style={{
      width: d,
      height: d,
      borderRadius: "50%",
      background: C.white,
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      boxSizing: "border-box",
    }}
  >
    <Img src={staticFile("brand/telu-mark.png")} style={{ height: d * 0.6 }} />
  </div>
);

const Ribbon: React.FC = () => {
  const f = useCurrentFrame();
  const appear = tween(f, 108, 126, 0, 1);
  const out = tween(f, 868, 884, 0, 1, (t) => t);
  const w = ribbonW(f);
  const cx = nodeX(w);
  const complete = tween(f, 645, 675, 0, 1);
  if (appear <= 0) return null;
  return (
    <div style={{ position: "absolute", inset: 0, opacity: appear * (1 - out * 0.0) }}>
      <div style={{ position: "absolute", left: 150, top: NODE_Y - 1.5, width: 1620, height: 3, background: "rgba(255,255,255,0.16)" }} />
      <div style={{ position: "absolute", left: 150, top: NODE_Y - 2, width: cx - 150, height: 4, background: C.accent, borderRadius: 2 }} />
      {Array.from({ length: 16 }, (_, i) => {
        const x = nodeX(i + 1);
        const done = x < cx - 1;
        const isCur = Math.abs(x - cx) < 1;
        if (isCur) return null;
        return (
          <div
            key={i}
            style={{
              position: "absolute",
              left: x - 8,
              top: NODE_Y - 8,
              width: 16,
              height: 16,
              borderRadius: "50%",
              background: done ? C.accent : C.stage,
              border: `2.5px solid ${done ? C.accent : "rgba(255,255,255,0.35)"}`,
              boxSizing: "border-box",
            }}
          />
        );
      })}
      <div style={{ position: "absolute", left: cx - 22, top: NODE_Y - 22, boxShadow: `0 0 0 5px ${C.accent}, 0 0 28px rgba(0,179,136,0.7)`, borderRadius: "50%" }}>
        <Mark d={44} />
      </div>
      <div style={{ position: "absolute", left: 0, top: NODE_Y + 36, width: 1920, fontFamily: FONT, fontSize: 18, fontWeight: 500, color: C.faintWhite }}>
        <span style={{ position: "absolute", left: 150 - 20, width: 40, textAlign: "center" }}>1</span>
        <span style={{ position: "absolute", left: 1770 - 20, width: 40, textAlign: "center", color: C.white, opacity: 0.5 + 0.5 * complete }}>16</span>
      </div>
    </div>
  );
};

/** Cakram logo: dari tengah layar ke simpul 1 pita (f96–126). */
const HeroDisc: React.FC = () => {
  const f = useCurrentFrame();
  const appear = tween(f, 80, 92, 0, 1);
  const trav = tween(f, 96, 126, 0, 1, EASE_IO);
  if (f < 80 || f >= 127) return null;
  const x = 960 + (nodeX(1) - 960) * trav;
  const y = 540 + (NODE_Y - 540) * trav;
  const d = 300 + (44 - 300) * trav;
  const ring = 1 - trav;
  return (
    <div
      style={{
        position: "absolute",
        left: x - d / 2,
        top: y - d / 2,
        opacity: appear,
        scale: String(0.7 + 0.3 * appear),
        boxShadow: `0 0 0 ${6 * (1 - trav) + 5 * trav}px ${C.accent}, 0 0 ${50 * ring}px rgba(0,179,136,0.6)`,
        borderRadius: "50%",
      }}
    >
      <Mark d={d} />
    </div>
  );
};

export const Bumper: React.FC = () => {
  const [handle] = useState(() => delayRender("Memuat font"));
  useEffect(() => {
    fontsReady.then(() => continueRender(handle)).catch((e) => cancelRender(e));
  }, [handle]);

  return (
    <AbsoluteFill style={{ background: C.stage, fontFamily: FONT }}>
      <Audio src={staticFile("audio/bumper.wav")} />
      <Stage />
      <Sequence from={S.hook[0]} durationInFrames={len("hook")} premountFor={30}>
        <Hook />
      </Sequence>
      <Sequence from={S.admin[0]} durationInFrames={len("admin")} premountFor={30}>
        <Admin d={len("admin")} />
      </Sequence>
      <Sequence from={S.mhs[0]} durationInFrames={len("mhs")} premountFor={30}>
        <Student d={len("mhs")} />
      </Sequence>
      <Sequence from={S.bimbingan[0]} durationInFrames={len("bimbingan")} premountFor={30}>
        <Bimbingan d={len("bimbingan")} />
      </Sequence>
      <Sequence from={S.rubrik[0]} durationInFrames={len("rubrik")} premountFor={30}>
        <Rubrik d={len("rubrik")} />
      </Sequence>
      <Sequence from={S.qr[0]} durationInFrames={len("qr")} premountFor={30}>
        <Qr d={len("qr")} />
      </Sequence>
      <Sequence from={S.rekap[0]} durationInFrames={len("rekap")} premountFor={30}>
        <Rekap d={len("rekap")} />
      </Sequence>
      <Sequence from={S.outro[0]} durationInFrames={len("outro")}>
        <Outro />
      </Sequence>
      <Ribbon />
      <Hud />
      <HeroDisc />
      <Grain />
    </AbsoluteFill>
  );
};
