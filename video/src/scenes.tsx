import React from "react";
import { Img, staticFile, useCurrentFrame } from "remotion";
import { C, EASE_IO, FONT, pulse, tween } from "./theme";
import { Card, CamLayer, Cover, Headline, MShot, Phone, Rect, Ring, Ripple, Shot, camera, useSceneMotion } from "./ui";

const CARD: Rect = { x: 664, y: 132, w: 1160, h: 760 };

/** Gerak masuk/keluar untuk ponsel (frame lokal). */
const usePhoneMotion = (f: number, inAt: number, d: number) => {
  const p = tween(f, inAt, inAt + 18, 0, 1);
  const out = tween(f, d - 8, d, 0, 1, (t) => t * t);
  return { opacity: p * (1 - out), ty: (1 - p) * 120 + out * 30, scale: 0.96 + 0.04 * p };
};

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

/* ------------------------------------------------------------ Admin (local 135) */

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
    { f: 100, v: { x: 940, y: 410, w: 750 } },
    { f: 124, v: { x: 500, y: 60, w: 1190 } },
  ]);
  const after = tween(f, 104, 116, 0, 1, (t) => t);
  const hl = (i: number) => tween(f, 18 + i * 10, 28 + i * 10, 1, 0, (t) => t);
  return (
    <>
      <Headline lines={["Syarat dicek", "otomatis."]} sub="Keputusan tetap di tangan admin." subAt={30} frame={f} outAt={d - 14} />
      <Card rect={CARD} opacity={m.opacity} tx={m.tx} scale={m.scale}>
        <CamLayer view={view} cardW={CARD.w}>
          <Shot name="admin-approval" />
          <Cover r={{ x: 975, y: 452, w: 520, h: 28 }} opacity={tween(f, 80, 88, 1, 0, (t) => t)} />
          {SCREEN_BANDS.map((b, i) => (
            <Cover key={i} r={{ x: 975, y: b[0], w: 520, h: b[1] - b[0] }} opacity={hl(i)} />
          ))}
          <Ring r={{ x: 1000, y: 782, w: 492, h: 70 }} p={tween(f, 76, 86, 0, 1) * (1 - after)} color={C.danger} />
          <Ring r={{ x: 1587, y: 616.6, w: 68, h: 36 }} p={tween(f, 88, 96, 0, 1) * (1 - after)} />
          <Ripple cx={1621} cy={634.6} frame={f} at={100} />
          <div style={{ opacity: after, position: "absolute", inset: 0 }}>
            <Shot name="admin-approval-after" />
          </div>
        </CamLayer>
      </Card>
    </>
  );
};

/* ------------------------------------------------------- Mahasiswa (local 90) */

export const Student: React.FC<{ d: number }> = ({ d }) => {
  const m = useSceneMotion(d);
  const f = m.frame;
  const view = camera(f, [
    { f: 0, v: { x: 500, y: 70, w: 1200 } },
    { f: 12, v: { x: 500, y: 70, w: 1200 } },
    { f: 58, v: { x: 500, y: 260, w: 1200 } },
  ]);
  return (
    <>
      <Headline lines={["Semua tahap tim,", "satu layar."]} sub="Topik, dosen, deadline, dan progres." subAt={26} frame={f} outAt={d - 12} />
      <Card rect={CARD} opacity={m.opacity} tx={m.tx} scale={m.scale}>
        <CamLayer view={view} cardW={CARD.w}>
          <Shot name="mhs-dash" />
          <Ring r={{ x: 1578, y: 146, w: 82, h: 60 }} p={pulse(f, 18, 28, 44, 52)} radius={12} />
          <Ring r={{ x: 572, y: 623, w: 50, h: 50 }} p={tween(f, 52, 64, 0, 1)} radius={25} pad={4} />
        </CamLayer>
      </Card>
    </>
  );
};

/* ----------------------------------------------------- Bimbingan (local 135) */

export const Bimbingan: React.FC<{ d: number }> = ({ d }) => {
  const f = useCurrentFrame();
  const r1: Rect = { x: 664, y: 260, w: 1160, h: 420 };
  const m1 = useSceneMotion(76, 0, 10);
  const pm = usePhoneMotion(f, 64, d);
  const view1 = camera(f, [
    { f: 0, v: { x: 830, y: 400, w: 860 } },
    { f: 44, v: { x: 830, y: 400, w: 860 } },
    { f: 66, v: { x: 488, y: 70, w: 1190 } },
  ]);
  const after = tween(f, 44, 52, 0, 1, (t) => t);
  return (
    <>
      <Headline
        lines={["Setiap sesi", "bimbingan,", "disetujui."]}
        sub="8 sesi menjadi syarat presentasi akhir."
        subAt={36}
        frame={f}
        outAt={d - 14}
      />
      {f < 78 && (
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
      {f >= 60 && (
        <Phone x={1230} y={150} h={740} {...pm}>
          <MShot name="m-mhs-dash-3" />
          <Ring r={{ x: 17, y: 68, w: 356, h: 130 }} p={pulse(f, 96, 106, 124, 134)} radius={16} pad={3} />
        </Phone>
      )}
    </>
  );
};

/* -------------------------------------------------------- Rubrik (local 120) */

export const Rubrik: React.FC<{ d: number }> = ({ d }) => {
  const m = useSceneMotion(d);
  const f = m.frame;
  const pm = usePhoneMotion(f, 74, d);
  const view = camera(f, [
    { f: 0, v: { x: 500, y: 160, w: 1190 } },
    { f: 62, v: { x: 500, y: 160, w: 1190 } },
    { f: 90, v: { x: 520, y: 490, w: 900 } },
    { f: 120, v: { x: 520, y: 490, w: 900 } },
  ]);
  return (
    <>
      <Headline lines={["Rubrik jadi", "nilai."]} sub="Dihitung otomatis, sesuai bobot." subAt={26} frame={f} outAt={d - 14} />
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
          <Cover r={{ x: 520, y: 340, w: 1150, h: 28 }} opacity={tween(f, 22, 58, 1, 0, (t) => t)} />
          <Ring r={{ x: 1002, y: 605, w: 96, h: 40 }} p={tween(f, 66, 78, 0, 1)} radius={8} pad={4} />
        </CamLayer>
      </Card>
      {f >= 70 && (
        <Phone x={1500} y={300} h={600} {...pm}>
          <MShot name="m-dosen-rubrik" />
        </Phone>
      )}
    </>
  );
};

/* ------------------------------------------- Email reminder (local 105) */

const EmailBody: React.FC<{ f: number; at: number }> = ({ f, at }) => {
  const line = (i: number) => {
    const p = tween(f, at + i * 6, at + i * 6 + 12, 0, 1);
    return { opacity: p, translate: `0 ${(1 - p) * 10}px` } as React.CSSProperties;
  };
  return (
    <div style={{ position: "absolute", inset: 0, background: C.white, fontFamily: FONT, color: C.ink, padding: "26px 24px" }}>
      <div style={{ display: "flex", alignItems: "center", gap: 14, ...line(0) }}>
        <div style={{ width: 46, height: 46, borderRadius: 23, background: C.paper, border: "1.5px solid #DDE3DF", display: "flex", alignItems: "center", justifyContent: "center" }}>
          <Img src={staticFile("brand/telu-mark.png")} style={{ height: 28 }} />
        </div>
        <div>
          <div style={{ fontSize: 16, fontWeight: 600 }}>Capstone FRI</div>
          <div style={{ fontSize: 13, color: "#6B7280" }}>kepada Dr. Maya Kartika</div>
        </div>
      </div>
      <div style={{ marginTop: 22, fontSize: 24, fontWeight: 700, letterSpacing: "-0.01em", lineHeight: 1.2, ...line(1) }}>[Capstone] Pengisian nilai</div>
      <div style={{ height: 1, background: "#E5E7EB", margin: "20px 0", ...line(1) }} />
      <div style={{ fontSize: 16, lineHeight: 1.5, ...line(2) }}>Yth. Dr. Maya Kartika,</div>
      <div style={{ fontSize: 16, lineHeight: 1.5, marginTop: 14, ...line(3) }}>
        Pengingat Capstone: Pengisian nilai belum diselesaikan untuk:
      </div>
      <div style={{ marginTop: 12, padding: "12px 14px", borderRadius: 10, background: "#ECF8F1", border: "1px solid #BFE6D0", fontSize: 15, fontWeight: 500, ...line(4) }}>
        • Tim Arunika · Pameran
      </div>
      <div style={{ marginTop: 22, display: "inline-block", padding: "13px 20px", borderRadius: 10, background: C.brand, color: C.white, fontSize: 15, fontWeight: 600, ...line(5) }}>
        Buka halaman pekerjaan
      </div>
      <div style={{ marginTop: 26, fontSize: 13, color: "#6B7280", lineHeight: 1.45, ...line(6) }}>
        Pesan otomatis dari Capstone FRI Telkom University.
      </div>
    </div>
  );
};

export const Email: React.FC<{ d: number }> = ({ d }) => {
  const R: Rect = { x: 664, y: 190, w: 1160, h: 400 };
  const m = useSceneMotion(d, 0, 8);
  const f = m.frame;
  const pm = usePhoneMotion(f, 54, d);
  const ready = tween(f, 8, 14, 0, 1, (t) => t);
  const log = tween(f, 30, 40, 0, 1, (t) => t);
  const dim = tween(f, 58, 74, 1, 0.3, (t) => t);
  const vA = { x: 500, y: 190, w: 1190 };
  const vB = { x: 500, y: 650, w: 1190 };
  return (
    <>
      <Headline
        lines={["Dosen telat", "menilai?"]}
        sub="Pengingat dikirim otomatis lewat email."
        subAt={28}
        frame={f}
        outAt={d - 14}
      />
      <Card rect={R} opacity={m.opacity * dim} tx={m.tx} scale={m.scale}>
        <CamLayer view={vA} cardW={R.w} opacity={1 - log}>
          <Shot name="admin-monitor" />
          <div style={{ position: "absolute", inset: 0, opacity: ready }}>
            <Shot name="admin-monitor-ready" />
          </div>
          <Ring r={{ x: 1372, y: 478, w: 287, h: 44 }} p={tween(f, 14, 22, 0, 1) * (1 - log)} radius={10} pad={4} />
          <Ripple cx={1515} cy={500} frame={f} at={26} />
        </CamLayer>
        <CamLayer view={vB} cardW={R.w} opacity={log}>
          <Shot name="admin-reminder-log" />
          <Ring r={{ x: 1395, y: 815, w: 100, h: 178 }} p={tween(f, 42, 52, 0, 1)} radius={12} pad={4} />
        </CamLayer>
      </Card>
      {f >= 52 && (
        <Phone x={1370} y={190} h={720} {...pm}>
          <EmailBody f={f} at={62} />
        </Phone>
      )}
    </>
  );
};

/* ---------------------------------------------------------- Rekap (local 75) */

export const Rekap: React.FC<{ d: number }> = ({ d }) => {
  const r: Rect = { x: 664, y: 270, w: 1160, h: 486 };
  const m = useSceneMotion(d, 0, 8);
  const f = m.frame;
  const view = { x: 500, y: 72, w: 1200 };
  const chip = tween(f, 44, 58, 0, 1);
  return (
    <>
      <Headline lines={["Rekap nilai", "otomatis."]} sub="Ekspor ke iGadis." subAt={22} frame={f} outAt={d - 10} />
      <Card rect={r} opacity={m.opacity} tx={m.tx} scale={m.scale}>
        <CamLayer view={view} cardW={r.w}>
          <Shot name="admin-recap" />
          {[0, 1, 2].map((i) => (
            <Cover key={i} r={{ x: 1000, y: 296 + i * 45, w: 700, h: 44 }} opacity={tween(f, 8 + i * 4, 18 + i * 4, 1, 0, (t) => t)} />
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
              opacity: pulse(f, 26, 34, 38, 46),
            }}
          />
          <Cover r={{ x: 1594, y: 242, w: 90, h: 134 }} opacity={1} />
          <Cover r={{ x: 1594, y: 376, w: 90, h: 46 }} opacity={1} color="#F5F5F7" />
          <Ring r={{ x: 1357.7, y: 128.4, w: 159.9, h: 44 }} p={tween(f, 34, 42, 0, 1)} />
          <Ripple cx={1437} cy={150} frame={f} at={40} />
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

/* ------------------------------------------------------ Outro + kredit (135) */

const TEAM = [
  { name: "Rizky", img: "team/rizky.jpg" },
  { name: "Via", img: "team/via.jpg" },
  { name: "Jay", img: "team/jay.jpg" },
];

export const Outro: React.FC = () => {
  const f = useCurrentFrame();
  const t1 = tween(f, 6, 26, 0, 1);
  const t2 = tween(f, 18, 38, 0, 1);
  const t3 = tween(f, 28, 48, 0, 1);
  const k = tween(f, 56, 84, 0, 1, EASE_IO); // bergeser ke atas saat kredit masuk
  const lerp = (a: number, b: number) => a + (b - a) * k;
  const c1 = tween(f, 70, 88, 0, 1);
  const c2 = tween(f, 78, 98, 0, 1);
  return (
    <div style={{ position: "absolute", inset: 0, fontFamily: FONT, textAlign: "center" }}>
      <div
        style={{
          position: "absolute",
          top: lerp(250, 52),
          width: "100%",
          fontSize: lerp(168, 112),
          fontWeight: 700,
          letterSpacing: "-0.04em",
          lineHeight: 1.1,
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
          top: lerp(470, 190),
          width: "100%",
          fontSize: lerp(34, 28),
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
          top: lerp(560, 250),
          left: 0,
          right: 0,
          display: "flex",
          justifyContent: "center",
          opacity: t2,
          translate: `0 ${(1 - t2) * 20}px`,
          scale: lerp(1, 0.72),
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
          opacity: t3 * (1 - k),
          translate: `0 ${(1 - t3) * 20}px`,
        }}
      >
        16 minggu. Satu alur.
      </div>

      {/* Kredit */}
      <div
        style={{
          position: "absolute",
          top: 384,
          width: "100%",
          fontSize: 24,
          fontWeight: 600,
          letterSpacing: "0.32em",
          color: C.faintWhite,
          opacity: c1,
          translate: `0 ${(1 - c1) * 14}px`,
        }}
      >
        CREATED BY
      </div>
      <div
        style={{
          position: "absolute",
          top: 424,
          width: "100%",
          fontSize: 84,
          fontWeight: 700,
          letterSpacing: "-0.03em",
          color: C.white,
          opacity: c1,
          translate: `0 ${(1 - c1) * 20}px`,
        }}
      >
        Du Bist Gut Genug
      </div>
      <div style={{ position: "absolute", top: 580, left: 0, right: 0, display: "flex", justifyContent: "center", gap: 90 }}>
        {TEAM.map((p, i) => {
          const a = tween(f, 86 + i * 7, 86 + i * 7 + 18, 0, 1);
          return (
            <div key={p.name} style={{ width: 230, opacity: a, translate: `0 ${(1 - a) * 28}px` }}>
              <div
                style={{
                  width: 210,
                  height: 210,
                  margin: "0 auto",
                  borderRadius: "50%",
                  overflow: "hidden",
                  border: `5px solid ${C.white}`,
                  boxShadow: `0 0 0 6px rgba(0,179,136,0.55), 0 24px 60px rgba(0,0,0,0.45)`,
                  background: C.paper,
                }}
              >
                <Img src={staticFile(p.img)} style={{ width: "100%", height: "100%", objectFit: "cover" }} />
              </div>
              <div style={{ marginTop: 22, fontSize: 36, fontWeight: 600, color: C.white, letterSpacing: "-0.01em" }}>{p.name}</div>
            </div>
          );
        })}
      </div>
    </div>
  );
};
