import React from "react";
import { Img, interpolate, staticFile, useCurrentFrame } from "remotion";
import { C, EASE_IO, EASE_OUT, FONT, pulse, tween } from "./theme";
import { Card, CamLayer, Cover, Headline, Rect, Ring, Ripple, Shot, camera, useSceneMotion } from "./ui";

const CARD: Rect = { x: 664, y: 132, w: 1160, h: 760 };

/* ------------------------------------------------------------- Hook (0–105) */

/** Foto gedung + teks pembuka. Cakram logo & perjalanannya ke pita ada di Bumper (lintas adegan). */
export const Hook: React.FC = () => {
  const f = useCurrentFrame();
  const FX = 968.4;
  const FY = 351.1;
  const zk = tween(f, 58, 92, 0, 1, EASE_IO);
  const Z = 1 + (0.05 * f) / 60 + zk * 2.2;
  const photoOut = tween(f, 86, 98, 1, 0, (t) => t);
  const words = [
    { t: "16 minggu.", at: 6, y: 0 },
    { t: "Tim. Topik. Dosen. Nilai.", at: 28, y: 1 },
    { t: "Satu alur.", at: 66, y: 2, accent: true },
  ];
  const textOut = tween(f, 82, 92, 0, 1, (t) => t);
  return (
    <div style={{ position: "absolute", inset: 0, overflow: "hidden" }}>
      <div style={{ position: "absolute", inset: 0, opacity: photoOut * tween(f, 0, 14, 0, 1) }}>
        <div
          style={{
            position: "absolute",
            inset: 0,
            transformOrigin: `${FX}px ${FY}px`,
            transform: `translate(${(960 - FX) * zk}px, ${(540 - FY) * zk}px) scale(${Z})`,
          }}
        >
          <Img
            src={staticFile("brand/login-hero.jpg")}
            style={{ position: "absolute", left: 0, top: -73.3, width: 1920, height: 1226.6 }}
          />
        </div>
        <div style={{ position: "absolute", inset: 0, background: C.stage, mixBlendMode: "multiply", opacity: 0.55 }} />
        <div
          style={{
            position: "absolute",
            inset: 0,
            background: `linear-gradient(90deg, ${C.stage} 0%, rgba(3,36,26,0.55) 45%, rgba(3,36,26,0) 80%)`,
          }}
        />
      </div>
      <div style={{ position: "absolute", left: 96, top: 380, fontFamily: FONT, opacity: 1 - textOut }}>
        {words.map((w) => {
          const p = tween(f, w.at, w.at + 18, 0, 1);
          const big = w.y !== 1;
          return (
            <div
              key={w.t}
              style={{
                fontSize: big ? 96 : 40,
                fontWeight: big ? 700 : 500,
                letterSpacing: big ? "-0.03em" : "-0.005em",
                lineHeight: big ? 1.05 : 1.7,
                color: w.accent ? C.accent : big ? C.white : C.softWhite,
                opacity: p,
                translate: `0 ${(1 - p) * 30}px`,
              }}
            >
              {w.t}
            </div>
          );
        })}
      </div>
    </div>
  );
};

/* ------------------------------------------------------------ Admin (local 150) */

const SCREEN_BANDS: [number, number][] = [
  [480, 540],
  [540, 596],
  [596, 652],
  [652, 722],
  [722, 776],
  [776, 862],
];

export const Admin: React.FC<{ d: number }> = ({ d }) => {
  const m = useSceneMotion(d);
  const f = m.frame;
  const view = camera(f, [
    { f: 0, v: { x: 940, y: 410, w: 750 } },
    { f: 112, v: { x: 940, y: 410, w: 750 } },
    { f: 140, v: { x: 500, y: 60, w: 1190 } },
  ]);
  const after = tween(f, 122, 134, 0, 1, (t) => t);
  const hl = (i: number) => tween(f, 22 + i * 12, 32 + i * 12, 1, 0, (t) => t);
  return (
    <>
      <Headline lines={["Syarat dicek", "otomatis."]} sub="Keputusan tetap di tangan admin." subAt={34} frame={f} outAt={d - 14} />
      <Card rect={CARD} opacity={m.opacity} tx={m.tx} scale={m.scale}>
        <CamLayer view={view} cardW={CARD.w}>
          <Shot name="admin-approval" />
          <Cover r={{ x: 975, y: 452, w: 520, h: 28 }} opacity={tween(f, 96, 106, 1, 0, (t) => t)} />
          {SCREEN_BANDS.map((b, i) => (
            <Cover key={i} r={{ x: 975, y: b[0], w: 520, h: b[1] - b[0] }} opacity={hl(i)} />
          ))}
          <Ring r={{ x: 1000, y: 782, w: 492, h: 70 }} p={tween(f, 92, 104, 0, 1) * (1 - after)} color={C.danger} />
          <Ring r={{ x: 1587, y: 616.6, w: 68, h: 36 }} p={tween(f, 106, 114, 0, 1) * (1 - after)} />
          <Ripple cx={1621} cy={634.6} frame={f} at={118} />
          <div style={{ opacity: after, position: "absolute", inset: 0 }}>
            <Shot name="admin-approval-after" />
          </div>
        </CamLayer>
      </Card>
    </>
  );
};

/* ------------------------------------------------------- Mahasiswa (local 105) */

export const Student: React.FC<{ d: number }> = ({ d }) => {
  const m = useSceneMotion(d);
  const f = m.frame;
  const view = camera(f, [
    { f: 0, v: { x: 500, y: 70, w: 1200 } },
    { f: 14, v: { x: 500, y: 70, w: 1200 } },
    { f: 66, v: { x: 500, y: 260, w: 1200 } },
  ]);
  return (
    <>
      <Headline lines={["Semua tahap tim,", "satu layar."]} sub="Topik, dosen, deadline, dan progres." subAt={30} frame={f} outAt={d - 14} />
      <Card rect={CARD} opacity={m.opacity} tx={m.tx} scale={m.scale}>
        <CamLayer view={view} cardW={CARD.w}>
          <Shot name="mhs-dash" />
          <Ring r={{ x: 1578, y: 146, w: 82, h: 60 }} p={pulse(f, 20, 32, 52, 62)} radius={12} />
          <Ring r={{ x: 572, y: 623, w: 50, h: 50 }} p={tween(f, 60, 74, 0, 1)} radius={25} pad={4} />
        </CamLayer>
      </Card>
    </>
  );
};

/* ----------------------------------------------------- Bimbingan (local 135) */

export const Bimbingan: React.FC<{ d: number }> = ({ d }) => {
  const f = useCurrentFrame();
  const r1: Rect = { x: 664, y: 260, w: 1160, h: 420 };
  const r2: Rect = { x: 864, y: 318, w: 760, h: 282 };
  const m1 = useSceneMotion(76, 0, 10);
  const m2 = (() => {
    const inP = tween(f, 62, 80, 0, 1);
    const outP = tween(f, d - 8, d, 0, 1, (t) => t * t);
    return { opacity: inP * (1 - outP), tx: (1 - inP) * 90 - outP * 40, scale: 0.985 + 0.015 * inP };
  })();
  const view1 = camera(f, [
    { f: 0, v: { x: 830, y: 400, w: 860 } },
    { f: 44, v: { x: 830, y: 400, w: 860 } },
    { f: 66, v: { x: 488, y: 70, w: 1190 } },
  ]);
  const after = tween(f, 44, 52, 0, 1, (t) => t);
  const sw = tween(f, 96, 108, 0, 1, (t) => t);
  const view2 = { x: 500, y: 234, w: 410 };
  return (
    <>
      <Headline
        lines={["Setiap sesi", "bimbingan,", "disetujui."]}
        sub="8 sesi menjadi syarat presentasi akhir."
        subAt={36}
        frame={f}
        outAt={d - 14}
      />
      {f < 74 && (
        <Card rect={r1} opacity={m1.opacity} tx={m1.tx} scale={m1.scale}>
          <CamLayer view={view1} cardW={r1.w}>
            <Shot name="dosen-log-before" />
            <Ring r={{ x: 1519, y: 470.6, w: 128, h: 36 }} p={tween(f, 22, 32, 0, 1) * (1 - after)} />
            <Ripple cx={1583} cy={488.6} frame={f} at={36} />
            <div style={{ opacity: after, position: "absolute", inset: 0 }}>
              <Shot name="dosen-log-after" />
            </div>
          </CamLayer>
        </Card>
      )}
      {f >= 62 && (
        <Card rect={r2} opacity={m2.opacity} tx={m2.tx} scale={m2.scale}>
          <CamLayer view={view2} cardW={r2.w}>
            <Shot name="mhs-dash-7" />
            <div style={{ opacity: sw, position: "absolute", inset: 0 }}>
              <Shot name="mhs-dash-8" />
            </div>
            <Ring r={{ x: 529, y: 253, w: 379, h: 133 }} p={pulse(f, 98, 108, 124, 134)} radius={14} pad={3} />
          </CamLayer>
        </Card>
      )}
    </>
  );
};

/* -------------------------------------------------------- Rubrik (local 135) */

export const Rubrik: React.FC<{ d: number }> = ({ d }) => {
  const m = useSceneMotion(d);
  const f = m.frame;
  const view = camera(f, [
    { f: 0, v: { x: 500, y: 160, w: 1190 } },
    { f: 66, v: { x: 500, y: 160, w: 1190 } },
    { f: 96, v: { x: 520, y: 490, w: 900 } },
    { f: 104, v: { x: 520, y: 490, w: 900 } },
    { f: 128, v: { x: 880, y: 490, w: 810 } },
  ]);
  return (
    <>
      <Headline lines={["Rubrik jadi", "nilai."]} sub="Dihitung otomatis, sesuai bobot." subAt={30} frame={f} outAt={d - 14} />
      <Card rect={CARD} opacity={m.opacity} tx={m.tx} scale={m.scale}>
        <CamLayer view={view} cardW={CARD.w}>
          <Shot name="dosen-rubrik" />
          <div
            style={{
              position: "absolute",
              left: 725,
              top: 268,
              width: 595 * tween(f, 8, 26, 0, 1),
              height: 4,
              background: C.accent,
              borderRadius: 2,
            }}
          />
          <Cover r={{ x: 520, y: 340, w: 1150, h: 28 }} opacity={tween(f, 24, 62, 1, 0, (t) => t)} />
          <Ring r={{ x: 1002, y: 605, w: 96, h: 40 }} p={pulse(f, 70, 82, 112, 124)} radius={8} pad={4} />
        </CamLayer>
      </Card>
    </>
  );
};

/* ------------------------------------------------------------ QR (local 120) */

export const Qr: React.FC<{ d: number }> = ({ d }) => {
  const m = useSceneMotion(d);
  const f = m.frame;
  const sw = tween(f, 66, 78, 0, 1, (t) => t);
  const view = camera(f, [
    { f: 0, v: { x: 696, y: 190, w: 800 } },
    { f: 66, v: { x: 696, y: 190, w: 800 } },
    { f: 78, v: { x: 500, y: 70, w: 1190 } },
  ]);
  // kisi-kisi bidik di atas QR (981,278,230×230)
  const conv = tween(f, 14, 34, 1, 0);
  const lock = tween(f, 62, 68, 0, 1);
  const pad = 26 + conv * 90;
  const q = { x: 981, y: 278, s: 230 };
  const corner = (cx: number, cy: number, sx: number, sy: number) => (
    <div
      key={`${cx}${cy}`}
      style={{
        position: "absolute",
        left: cx + sx * pad - (sx > 0 ? 36 : 0) - (sx > 0 ? 0 : 0),
        top: cy + sy * pad - (sy > 0 ? 36 : 0),
        width: 36,
        height: 36,
        borderTop: sy < 0 ? `5px solid ${C.accent}` : undefined,
        borderBottom: sy > 0 ? `5px solid ${C.accent}` : undefined,
        borderLeft: sx < 0 ? `5px solid ${C.accent}` : undefined,
        borderRight: sx > 0 ? `5px solid ${C.accent}` : undefined,
        opacity: tween(f, 12, 20, 0, 1) * (1 - sw),
        filter: lock > 0.5 ? `drop-shadow(0 0 10px ${C.accent})` : undefined,
      }}
    />
  );
  const scanY = tween(f, 34, 62, q.y, q.y + q.s, (t) => t);
  return (
    <>
      <Headline lines={["Scan QR.", "Langsung ke", "penilaian."]} sub="Hanya dosen yang berwenang." subAt={34} frame={f} outAt={d - 14} />
      <Card rect={CARD} opacity={m.opacity} tx={m.tx} scale={m.scale}>
        <CamLayer view={view} cardW={CARD.w}>
          <Shot name="mhs-qr" />
          <div style={{ opacity: sw, position: "absolute", inset: 0 }}>
            <Shot name="dosen-pameran" />
          </div>
          {corner(q.x, q.y, -1, -1)}
          {corner(q.x + q.s, q.y, 1, -1)}
          {corner(q.x, q.y + q.s, -1, 1)}
          {corner(q.x + q.s, q.y + q.s, 1, 1)}
          {f >= 34 && f < 64 && (
            <div
              style={{
                position: "absolute",
                left: q.x - 10,
                top: scanY,
                width: q.s + 20,
                height: 3,
                background: C.accent,
                boxShadow: `0 0 18px ${C.accent}`,
                opacity: 1 - sw,
              }}
            />
          )}
          <Ring r={{ x: 509, y: 173, w: 1174, h: 44 }} p={tween(f, 84, 96, 0, 1)} radius={10} pad={4} />
        </CamLayer>
      </Card>
    </>
  );
};

/* ---------------------------------------------------------- Rekap (local 90) */

export const Rekap: React.FC<{ d: number }> = ({ d }) => {
  const r: Rect = { x: 664, y: 270, w: 1160, h: 486 };
  const m = useSceneMotion(d);
  const f = m.frame;
  const view = { x: 500, y: 72, w: 1200 };
  const chip = tween(f, 56, 72, 0, 1);
  return (
    <>
      <Headline lines={["Rekap nilai", "otomatis."]} sub="Ekspor ke iGadis." subAt={30} frame={f} outAt={d - 10} />
      <Card rect={r} opacity={m.opacity} tx={m.tx} scale={m.scale}>
        <CamLayer view={view} cardW={r.w}>
          <Shot name="admin-recap" />
          {[0, 1, 2].map((i) => (
            <Cover key={i} r={{ x: 1000, y: 296 + i * 45, w: 700, h: 44 }} opacity={tween(f, 10 + i * 4, 22 + i * 4, 1, 0, (t) => t)} />
          ))}
          <div
            style={{
              position: "absolute",
              left: 1536,
              top: 248,
              width: 64,
              height: 150,
              borderRadius: 10,
              background: "rgba(0,179,136,0.14)",
              border: `2px solid ${C.accent}`,
              opacity: pulse(f, 34, 44, 48, 56),
            }}
          />
          <Cover r={{ x: 1594, y: 242, w: 90, h: 134 }} opacity={1} />
          <Cover r={{ x: 1594, y: 376, w: 90, h: 46 }} opacity={1} color="#F5F5F7" />
          <Ring r={{ x: 1357.7, y: 128.4, w: 159.9, h: 44 }} p={tween(f, 48, 58, 0, 1)} />
          <Ripple cx={1437} cy={150} frame={f} at={52} />
        </CamLayer>
        <div
          style={{
            position: "absolute",
            right: 28,
            bottom: 26,
            padding: "14px 22px",
            borderRadius: 12,
            background: C.ink,
            color: C.white,
            fontFamily: FONT,
            fontWeight: 500,
            fontSize: 24,
            opacity: chip,
            translate: `0 ${(1 - chip) * 24}px`,
          }}
        >
          nilai-capstone-igadis-20261105-190000.xlsx
        </div>
      </Card>
    </>
  );
};

/* -------------------------------------------------------------- Outro (60) */

export const Outro: React.FC = () => {
  const f = useCurrentFrame();
  const t1 = tween(f, 6, 26, 0, 1);
  const t2 = tween(f, 18, 38, 0, 1);
  const t3 = tween(f, 28, 48, 0, 1);
  return (
    <div style={{ position: "absolute", inset: 0, fontFamily: FONT, textAlign: "center" }}>
      <div
        style={{
          position: "absolute",
          top: 250,
          width: "100%",
          fontSize: 168,
          fontWeight: 700,
          letterSpacing: "-0.04em",
          color: C.white,
          opacity: t1,
          translate: `0 ${(1 - t1) * 34}px`,
        }}
      >
        Capstone
      </div>
      <div
        style={{
          position: "absolute",
          top: 470,
          width: "100%",
          fontSize: 34,
          fontWeight: 500,
          color: C.softWhite,
          opacity: t2,
          translate: `0 ${(1 - t2) * 20}px`,
        }}
      >
        Capstone Design &amp; Project · Fakultas Rekayasa Industri
      </div>
      <div
        style={{
          position: "absolute",
          top: 560,
          left: 0,
          right: 0,
          display: "flex",
          justifyContent: "center",
          opacity: t2,
          translate: `0 ${(1 - t2) * 20}px`,
        }}
      >
        <div
          style={{
            display: "flex",
            alignItems: "center",
            gap: 36,
            background: C.white,
            padding: "22px 40px",
            borderRadius: 20,
            boxShadow: "0 24px 70px rgba(0,0,0,0.4)",
          }}
        >
          <Img src={staticFile("brand/telu-horizontal.png")} style={{ height: 64 }} />
          <div style={{ width: 2, height: 56, background: "#D8DDD9" }} />
          <Img src={staticFile("brand/fri-horizontal.png")} style={{ height: 64 }} />
        </div>
      </div>
      <div
        style={{
          position: "absolute",
          top: 740,
          width: "100%",
          fontSize: 44,
          fontWeight: 600,
          letterSpacing: "-0.01em",
          color: C.accent,
          opacity: t3,
          translate: `0 ${(1 - t3) * 20}px`,
        }}
      >
        16 minggu. Satu alur.
      </div>
    </div>
  );
};

export { interpolate };
