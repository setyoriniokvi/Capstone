import React from "react";
import { Img, interpolate, staticFile, useCurrentFrame } from "remotion";
import { C, EASE_IO, EASE_OUT, FONT } from "./theme";
import { Phone } from "./ui";
import off from "./offsets.json";
import { Bg, Burst, Chip, Cursor, Cut, Giant, Hi, MImg, Plane, RiseLines, clamp01, lerp, pop, ramp, size } from "./kit";

/** Warna latar tiap adegan — dipakai juga oleh gelombang transisi di Bumper. */
export const BG = {
  s1: C.stage,
  s2: "#F3F6F1",
  s3: C.brand,
  s4: "#E6F2EA",
  s5: "#08150F",
  s6: C.accent,
  s7: "#F3F6F1",
  s8: C.stage,
};

/** Titik klik (koordinat layar) tempat gelombang transisi berawal. */
export const WIPE = {
  w1: { x: 960, y: 430 },
  w2: { x: 1542, y: 444 },
  w3: { x: 1650, y: 330 },
  w4: { x: 1585, y: 540 },
  w5: { x: 1565, y: 520 },
  w6: { x: 1520, y: 520 },
  w7: { x: 330, y: 768 },
};

const phoneW = (h: number) => ((h - 18) * 390) / 844 + 18;

/** Ponsel dengan kemiringan 3D. */
const Phone3D: React.FC<{ x: number; y: number; h: number; ry?: number; rx?: number; rz?: number; ty?: number; opacity?: number; children: React.ReactNode }> = ({
  x,
  y,
  h,
  ry = -10,
  rx = 5,
  rz = 0,
  ty = 0,
  opacity = 1,
  children,
}) => (
  <div style={{ position: "absolute", inset: 0, perspective: 2400, perspectiveOrigin: "50% 40%" }}>
    <div style={{ position: "absolute", inset: 0, transform: `translateY(${ty}px) rotateY(${ry}deg) rotateX(${rx}deg) rotateZ(${rz}deg)`, transformOrigin: `${x + phoneW(h) / 2}px ${y + h / 2}px`, opacity }}>
      <Phone x={x} y={y} h={h}>
        {children}
      </Phone>
    </div>
  </div>
);

const Disc: React.FC<{ d: number; ring?: number }> = ({ d, ring = 6 }) => (
  <div
    style={{
      width: d,
      height: d,
      borderRadius: "50%",
      background: C.white,
      boxShadow: `0 0 0 ${ring}px ${C.accent}, 0 30px 80px rgba(0,0,0,0.4)`,
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
    }}
  >
    <Img src={staticFile("brand/telu-mark.png")} style={{ height: d * 0.6 }} />
  </div>
);

/* ===================================================================== S1 */

export const S1: React.FC = () => {
  const f = useCurrentFrame();
  const disc = pop(f, 2, 20);
  const letters = "Capstone".split("");
  const subA = ramp(f, 30, 44) * (1 - ramp(f, 54, 62, (t) => t));
  const subB = ramp(f, 56, 70);
  return (
    <div style={{ position: "absolute", inset: 0, overflow: "hidden", fontFamily: FONT }}>
      <Bg color={BG.s1} glow={C.stageLift} gx={960} gy={430} />
      {[0, 1, 2].map((i) => {
        const p = ramp(f, 10 + i * 9, 50 + i * 9, (t) => 1 - Math.pow(1 - t, 3));
        const d = 260 + p * 900;
        return (
          <div key={i} style={{ position: "absolute", left: 960 - d / 2, top: 430 - d / 2, width: d, height: d, borderRadius: "50%", border: `3px solid ${C.accent}`, opacity: (1 - p) * 0.7 }} />
        );
      })}
      <div style={{ position: "absolute", left: 960 - 130, top: 430 - 130, transform: `scale(${disc})`, opacity: clamp01(disc * 2) }}>
        <Disc d={260} />
      </div>
      <div style={{ position: "absolute", left: 0, right: 0, top: 590, textAlign: "center", fontSize: 190, fontWeight: 800, letterSpacing: "-0.045em", color: C.white, lineHeight: 1 }}>
        {letters.map((l, i) => {
          const p = ramp(f, 14 + i * 2.2, 14 + i * 2.2 + 16);
          return (
            <span key={i} style={{ display: "inline-block", transform: `translateY(${(1 - p) * 120}px) rotate(${(1 - p) * 8}deg)`, opacity: clamp01(p * 2) }}>
              {l}
            </span>
          );
        })}
      </div>
      <div style={{ position: "absolute", left: 0, right: 0, top: 830, textAlign: "center", fontSize: 42, fontWeight: 500, color: C.softWhite, opacity: subA, transform: `translateY(${(1 - subA) * 14}px)` }}>
        Dari pendaftaran tim sampai nilai akhir.
      </div>
      <div style={{ position: "absolute", left: 0, right: 0, top: 826, textAlign: "center", fontSize: 50, fontWeight: 700, color: C.accent, opacity: subB, transform: `translateY(${(1 - subB) * 14}px)`, letterSpacing: "-0.01em" }}>
        16 minggu. Satu alur.
      </div>
    </div>
  );
};

/* ===================================================================== S2 */

const ROW_H = [44, 44, 44, 54, 44, 68];

export const S2: React.FC = () => {
  const f = useCurrentFrame();
  const s = 1.3;
  const x0 = 740;
  let y = 200;
  const ys = ROW_H.map((h) => {
    const cur = y;
    y += h * s + 12;
    return cur;
  });
  const press = interpolate(f, [98, 100, 105], [0, 1, 0], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  const flash = pop(f, 104, 16);
  return (
    <div style={{ position: "absolute", inset: 0, overflow: "hidden", fontFamily: FONT }}>
      <Bg color={BG.s2} glow="#DDEBE2" gx={1500} gy={260} />
      <Giant f={f} text="01" x={1080} y={560} color="rgba(0,132,61,0.07)" size={700} drift={-0.5} />
      <Chip f={f} at={2} text="ADMIN" x={96} y={150} />
      <RiseLines f={f} at={6} x={96} y={210} size={92} lines={[{ t: "Syarat dicek" }, { t: "otomatis.", color: C.brand }]} />
      <div style={{ position: "absolute", left: 96, top: 420, fontSize: 34, fontWeight: 500, color: "#3E5A4B", opacity: ramp(f, 26, 40), transform: `translateY(${(1 - ramp(f, 26, 40)) * 14}px)` }}>
        Keputusan tetap di tangan admin.
      </div>
      <Cut name="adm-td-anggota" x={110} y={530} s={1.5} clip={{ x: 0, y: 0, w: 235, h: 90 }} bg="#FFFFFF" pad={14} radius={4} opacity={ramp(f, 30, 44)} ty={(1 - ramp(f, 30, 44)) * 20} />

      {ROW_H.map((h, i) => {
        const at = 14 + i * 9;
        const p = pop(f, at, 16);
        const fail = i === 5;
        const shake = fail ? Math.sin((f - at - 12) * 1.9) * 7 * (1 - ramp(f, at + 12, at + 34, (t) => t)) * (f > at + 12 ? 1 : 0) : 0;
        return (
          <Cut key={i} name={`adm-chk${i}`} x={x0} y={ys[i]} s={s} radius={4} bg="#FFFFFF" pad={10} tx={(1 - p) * 170 + shake} rot={(1 - p) * 4} opacity={ramp(f, at, at + 6, (t) => t)} k={0.96 + 0.04 * clamp01(p)}>
            {fail && <Hi r={{ x: -10, y: -10, w: 524, h: 88 }} p={ramp(f, at + 14, at + 24)} color={C.danger} radius={14} pad={0} w={3} />}
          </Cut>
        );
      })}

      <Cut name="adm-btn-tolak" x={1440} y={250} s={3} radius={6} opacity={ramp(f, 64, 70, (t) => t)} k={pop(f, 64, 16)} />
      <Cut name="adm-btn-terima" x={1440} y={390} s={3} radius={6} opacity={ramp(f, 70, 76, (t) => t)} k={pop(f, 70, 16) * (1 - press * 0.1)}>
        <Hi r={{ x: 0, y: 0, w: 68, h: 36 }} p={ramp(f, 84, 96)} radius={8} pad={3} w={2} />
      </Cut>
      <Burst f={f} at={100} x={WIPE.w2.x} y={WIPE.w2.y} color={C.brand} max={200} />
      <Cursor f={f} way={[{ f: 62, x: 1800, y: 780 }, { f: 94, x: WIPE.w2.x, y: WIPE.w2.y }]} pressAt={100} />

      <Cut name="adm-flash" x={431} y={872} s={0.9} radius={10} k={flash} opacity={ramp(f, 104, 110, (t) => t)} ty={(1 - clamp01(flash)) * 40} />
    </div>
  );
};

/* ===================================================================== S3 */

export const S3: React.FC = () => {
  const f = useCurrentFrame();
  const bob = (ph: number, a = 7) => Math.sin(f / 16 + ph) * a;
  const phoneIn = ramp(f, 0, 30);
  const sc1 = ramp(f, 36, 50, (t) => t);
  const sc2 = ramp(f, 66, 80, (t) => t);
  const slide = (a: number, from: number) => (1 - ramp(f, a, a + 22)) * from;
  return (
    <div style={{ position: "absolute", inset: 0, overflow: "hidden", fontFamily: FONT }}>
      <Bg color={BG.s3} glow="#10A653" gx={960} gy={420} />
      <Giant f={f} text="02" x={-60} y={380} color="rgba(255,255,255,0.07)" size={760} drift={0.5} />
      <RiseLines f={f} at={2} x={0} y={56} size={150} width={1920} align="center" lines={[{ t: "Satu layar.", color: C.white }]} />
      <div style={{ position: "absolute", left: 0, right: 0, top: 232, textAlign: "center", fontSize: 34, fontWeight: 500, color: "rgba(255,255,255,0.85)", opacity: ramp(f, 14, 30) }}>
        Topik, dosen, deadline, dan progres tim.
      </div>

      <Phone3D x={766} y={330} h={820} ry={lerp(-26, -9, phoneIn)} rx={lerp(14, 5, phoneIn)} ty={(1 - phoneIn) * 220}>
        <MImg name="m-dash-top" opacity={1} />
        <MImg name="m-dash-scroll" opacity={sc1} ty={(1 - sc1) * 40} />
        <MImg name="m-dash-scroll2" opacity={sc2} ty={(1 - sc2) * 40} />
      </Phone3D>

      <Cut name="mhs-p0" x={86} y={360} s={1.5} radius={14} rot={-4} tx={slide(8, -560)} ty={bob(0)} />
      <Cut name="mhs-p1" x={150} y={590} s={1.5} radius={14} rot={3} tx={slide(16, -620)} ty={bob(1.5)} />
      <Cut name="mhs-p2" x={1262} y={360} s={1.5} radius={14} rot={4} tx={slide(12, 620)} ty={bob(3)} />
      <Cut name="mhs-hero" x={1400} y={590} s={2.4} clip={{ x: 1054, y: 0, w: 122, h: 149 }} radius={22} rot={-3} tx={slide(22, 620)} ty={bob(4.5)}>
        <Hi r={{ x: 6, y: 6, w: 110, h: 137 }} p={ramp(f, 50, 62) * (0.6 + 0.4 * Math.sin(f / 6))} color={C.white} radius={16} pad={0} w={2} />
      </Cut>
    </div>
  );
};

/* ===================================================================== S4 */

export const S4: React.FC = () => {
  const f = useCurrentFrame();
  const press = interpolate(f, [38, 40, 45], [0, 1, 0], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  const to8 = ramp(f, 48, 58, (t) => t);
  const bump = 1 + 0.06 * Math.sin(clamp01((f - 48) / 14) * Math.PI);
  const flash = pop(f, 44, 16);
  const rowX = 96;
  const rowY = 430;
  const rs = 0.9;
  const bx = rowX + (off.dlogRow.btnSetujui.x + off.dlogRow.btnSetujui.w / 2) * rs;
  const by = rowY + (off.dlogRow.btnSetujui.y + off.dlogRow.btnSetujui.h / 2) * rs;
  const ph = pop(f, 58, 22);
  return (
    <div style={{ position: "absolute", inset: 0, overflow: "hidden", fontFamily: FONT }}>
      <Bg color={BG.s4} glow="#CFE8DA" gx={1500} gy={540} />
      <Giant f={f} text="14" x={900} y={500} color="rgba(0,132,61,0.07)" size={720} drift={-0.6} />
      <Chip f={f} at={2} text="DOSEN" x={96} y={70} />
      <RiseLines f={f} at={6} x={96} y={122} size={96} lines={[{ t: "Bimbingan" }, { t: "disetujui.", color: C.brand }]} />
      <div style={{ position: "absolute", left: 96, top: 340, fontSize: 32, fontWeight: 500, color: "#3E5A4B", opacity: ramp(f, 20, 34) }}>Progres tim ikut naik, seketika.</div>

      <Cut name="dlog-row" x={rowX} y={rowY} s={rs} radius={12} tx={(1 - ramp(f, 8, 30)) * -300} opacity={ramp(f, 8, 16, (t) => t)}>
        <Hi r={off.dlogRow.btnSetujui} p={ramp(f, 24, 36) * (1 - ramp(f, 44, 50, (t) => t))} radius={8} pad={3} w={2} />
      </Cut>
      <Burst f={f} at={40} x={bx} y={by} color={C.brand} max={170} />
      <Cursor f={f} way={[{ f: 14, x: 1500, y: 820 }, { f: 36, x: bx, y: by }, { f: 60, x: bx + 90, y: by + 40 }]} pressAt={40} opacity={1 - ramp(f, 60, 68, (t) => t)} />

      <Cut name="dlog-flash" x={rowX} y={548} s={0.9} radius={10} k={flash} opacity={ramp(f, 44, 50, (t) => t)} />

      <Cut name="mhs7-p0" x={rowX} y={640} s={2.3} radius={14} opacity={ramp(f, 18, 30, (t) => t)} ty={(1 - ramp(f, 18, 34)) * 60} k={bump}>
        <div style={{ position: "absolute", inset: 0, opacity: to8 }}>
          <Img src={staticFile("ui/mhs8-p0.png")} style={{ position: "absolute", left: 0, top: 0, width: size("mhs7-p0").w, height: size("mhs7-p0").h }} />
        </div>
        <Hi r={{ x: 0, y: 0, w: size("mhs7-p0").w, h: size("mhs7-p0").h }} p={pulseRing(f, 52, 60, 80, 90)} radius={14} pad={0} w={2} />
      </Cut>

      <Phone3D x={1400} y={150} h={780} ry={lerp(18, -8, ph)} rx={lerp(8, 4, ph)} ty={(1 - clamp01(ph)) * 560} opacity={clamp01(ph * 3)}>
        <MImg name="m-dash-8" />
      </Phone3D>
    </div>
  );
};

const pulseRing = (f: number, a: number, b: number, c: number, d: number) => interpolate(f, [a, b, c, d], [0, 1, 1, 0], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });

/* ===================================================================== S5 */

export const S5: React.FC = () => {
  const f = useCurrentFrame();
  const rows = [0, 1, 2];
  const rx = 96;
  const ry = (i: number) => 385 + i * 150;
  const rs = 1.45;
  const nx = rx + (224 + 80 + 144) * rs; // tepi kanan sel Nilai
  const eq = { x: 790, y: 520, w: 570, h: 176 };
  const ops = ["×", "+", "÷", "−"];
  const oi = f < 64 ? 0 : Math.floor((f - 64) / 16) % 4;
  const opFlip = f < 64 ? 0 : ((f - 64) % 16) / 16;
  const dotPos = (i: number, t: number) => {
    const p0 = { x: nx, y: ry(i) + 50 * rs };
    const p2 = { x: eq.x, y: eq.y + eq.h / 2 };
    const p1 = { x: (p0.x + p2.x) / 2, y: p0.y - 90 + i * 60 };
    const a = (1 - t) * (1 - t);
    const b = 2 * (1 - t) * t;
    const c = t * t;
    return { x: a * p0.x + b * p1.x + c * p2.x, y: a * p0.y + b * p1.y + c * p2.y };
  };
  const count = ramp(f, 66, 98, (t) => 1 - Math.pow(1 - t, 3));
  const total = (94.6 * count).toFixed(2).replace(".", ",");
  const resultIn = pop(f, 58, 20);
  return (
    <div style={{ position: "absolute", inset: 0, overflow: "hidden", fontFamily: FONT }}>
      <Bg color={BG.s5} glow="#0F4A2D" gx={1400} gy={500} />
      <Giant f={f} text="15" x={1000} y={380} color="rgba(0,179,136,0.07)" size={760} drift={-0.5} />
      <RiseLines f={f} at={2} x={96} y={60} size={112} lines={[{ t: "Rubrik jadi nilai.", color: C.white }]} />
      <div style={{ position: "absolute", left: 100, top: 196, fontSize: 32, fontWeight: 500, color: C.accent, opacity: ramp(f, 16, 30) }}>Rumus penilaian bisa disesuaikan.</div>
      <Cut name="rub-p0" x={96} y={262} s={0.85} clip={{ x: 0, y: 0, w: 1176, h: 70 }} radius={14} opacity={ramp(f, 8, 18, (t) => t)} ty={(1 - ramp(f, 8, 24)) * 30}>
        <Hi r={{ x: 0, y: 0, w: 1176, h: 70 }} p={pulseRing(f, 26, 34, 44, 52)} radius={14} pad={0} w={3} />
      </Cut>

      {rows.map((i) => {
        const at = 18 + i * 8;
        const p = pop(f, at, 16);
        const o = ramp(f, at, at + 6, (t) => t);
        const y = ry(i);
        return (
          <React.Fragment key={i}>
            <Cut name={`rub-ind${i}`} x={rx} y={y} s={rs} clip={{ x: 0, y: 0, w: 224, h: 100 }} shadow={false} tx={(1 - p) * -120} opacity={o} />
            <Cut name={`rub-bobot${i}`} x={rx + 224 * rs} y={y} s={rs} clip={{ x: 0, y: 0, w: 80, h: 100 }} shadow={false} tx={(1 - p) * -120} opacity={o} />
            <Cut name={`rub-nilai${i}`} x={rx + 304 * rs} y={y} s={rs} clip={{ x: 0, y: 0, w: 144, h: 100 }} shadow={false} tx={(1 - p) * -120} opacity={o}>
              <Hi r={{ x: 0, y: 0, w: 144, h: 100 }} p={ramp(f, 30 + i * 6, 38 + i * 6) * (1 - ramp(f, 56, 64, (t) => t))} radius={4} pad={0} w={3} />
            </Cut>
          </React.Fragment>
        );
      })}

      <div
        style={{
          position: "absolute",
          left: eq.x,
          top: eq.y,
          width: eq.w,
          height: eq.h,
          borderRadius: 26,
          background: C.white,
          transform: `scale(${pop(f, 40, 18)})`,
          opacity: clamp01(pop(f, 40, 18) * 2),
          boxShadow: "0 24px 70px rgba(0,0,0,0.45)",
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          justifyContent: "center",
          gap: 6,
        }}
      >
        <div style={{ position: "absolute", left: 26, top: 20, fontSize: 18, fontWeight: 700, letterSpacing: "0.18em", color: "#6B7280" }}>RUMUS PENILAIAN</div>
        <div style={{ position: "absolute", right: 20, top: 14, padding: "6px 14px", borderRadius: 999, background: "#D9F5E8", color: C.deep, fontSize: 18, fontWeight: 700, opacity: ramp(f, 52, 62), transform: `scale(${pop(f, 52, 14)})` }}>Dapat disesuaikan</div>
        <div style={{ display: "flex", alignItems: "center", gap: 9, marginTop: 36 }}>
          {["Skor", "Bobot", "Faktor"].map((t, i) => (
            <React.Fragment key={t}>
              <div style={{ padding: "8px 14px", borderRadius: 12, background: "#ECF8F1", border: "1.5px solid #BFE6D0", fontSize: 24, fontWeight: 700, color: C.ink, transform: `scale(${pop(f, 44 + i * 5, 14)})`, opacity: clamp01(pop(f, 44 + i * 5, 14) * 2) }}>{t}</div>
              <div style={{ width: 24, textAlign: "center", fontSize: 28, fontWeight: 800, color: C.brand, opacity: ramp(f, 48 + i * 5, 58 + i * 5), transform: `scaleY(${Math.abs(Math.cos(opFlip * Math.PI * (f < 64 ? 0 : 1)))})` }}>{ops[(oi + i) % 4]}</div>
            </React.Fragment>
          ))}
          <div style={{ padding: "8px 14px", borderRadius: 12, border: `2px dashed ${C.accent}`, fontSize: 22, fontWeight: 700, color: C.deep, whiteSpace: "nowrap", transform: `scale(${pop(f, 62, 14)})`, opacity: clamp01(pop(f, 62, 14) * 2) }}>+ variabel</div>
        </div>
      </div>

      {rows.map((i) => {
        const t0 = 34 + i * 6;
        const t = ramp(f, t0, t0 + 24, EASE_IO);
        if (f < t0 || t >= 1) return null;
        const pnt = dotPos(i, t);
        return (
          <div key={i} style={{ position: "absolute", left: pnt.x - 13, top: pnt.y - 13, width: 26, height: 26, borderRadius: "50%", background: C.accent, boxShadow: `0 0 26px 8px ${C.accent}`, zIndex: 20 }} />
        );
      })}

      {/* panah ke hasil */}
      <div style={{ position: "absolute", left: eq.x + eq.w, top: eq.y + eq.h / 2 - 3, width: 90 * ramp(f, 52, 66), height: 6, background: C.accent, borderRadius: 3, boxShadow: `0 0 16px ${C.accent}` }} />

      <div
        style={{
          position: "absolute",
          left: 1430,
          top: 406,
          width: 420,
          height: 400,
          borderRadius: 32,
          background: C.white,
          transform: `scale(${resultIn})`,
          opacity: clamp01(resultIn * 2),
          boxShadow: "0 30px 90px rgba(0,0,0,0.5)",
          padding: "38px 40px",
          boxSizing: "border-box",
        }}
      >
        <div style={{ fontSize: 28, fontWeight: 600, color: "#6B7280" }}>Nilai akhir</div>
        <div style={{ fontSize: 124, fontWeight: 800, letterSpacing: "-0.05em", color: C.brand, lineHeight: 1.05, marginTop: 14, fontVariantNumeric: "tabular-nums" }}>{total}</div>
        <div style={{ fontSize: 26, fontWeight: 500, color: "#6B7280", marginTop: 4 }}>dari 100 poin</div>
        <div style={{ marginTop: 28, height: 14, borderRadius: 7, background: "#E5E7EB", overflow: "hidden" }}>
          <div style={{ width: `${94.6 * count}%`, height: "100%", background: `linear-gradient(90deg, ${C.brand}, ${C.accent})` }} />
        </div>
      </div>
    </div>
  );
};

/* ===================================================================== S6 */

const EmailScreen: React.FC<{ f: number; at: number }> = ({ f, at }) => {
  const line = (i: number): React.CSSProperties => {
    const p = ramp(f, at + i * 4, at + i * 4 + 10);
    return { opacity: p, transform: `translateY(${(1 - p) * 10}px)` };
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
      <div style={{ fontSize: 16, lineHeight: 1.5, marginTop: 14, ...line(3) }}>Pengingat Capstone: Pengisian nilai belum diselesaikan untuk:</div>
      <div style={{ marginTop: 12, padding: "12px 14px", borderRadius: 10, background: "#ECF8F1", border: "1px solid #BFE6D0", fontSize: 15, fontWeight: 500, ...line(4) }}>• Tim Arunika · Pameran</div>
      <div style={{ marginTop: 22, display: "inline-block", padding: "13px 20px", borderRadius: 10, background: C.brand, color: C.white, fontSize: 15, fontWeight: 600, ...line(5) }}>Buka halaman pekerjaan</div>
      <div style={{ marginTop: 26, fontSize: 13, color: "#6B7280", lineHeight: 1.45, ...line(6) }}>Pesan otomatis dari Capstone FRI Telkom University.</div>
    </div>
  );
};

export const S6: React.FC = () => {
  const f = useCurrentFrame();
  const ps = 0.92;
  const px = 96;
  const py = 270;
  const send = off.mon.send;
  const sx = px + (send.x + send.w / 2) * ps;
  const sy = py + (send.y + send.h / 2) * ps;
  const cb0 = { x: px + (off.mon.cb0.x + 10) * ps, y: py + (off.mon.cb0.y + 10) * ps };
  const cb1 = { x: px + (off.mon.cb1.x + 10) * ps, y: py + (off.mon.cb1.y + 10) * ps };
  const checked = ramp(f, 21, 25, (t) => t);
  const logIn = pop(f, 48, 20);
  const phIn = pop(f, 6, 24);
  // pesawat kertas: kurva dari tombol kirim ke ponsel
  const pt = ramp(f, 42, 66, EASE_IO);
  const p0 = { x: sx, y: sy };
  const p2 = { x: 1500, y: 330 };
  const p1 = { x: 1180, y: 260 };
  const bez = (t: number) => ({
    x: (1 - t) * (1 - t) * p0.x + 2 * (1 - t) * t * p1.x + t * t * p2.x,
    y: (1 - t) * (1 - t) * p0.y + 2 * (1 - t) * t * p1.y + t * t * p2.y,
  });
  const here = bez(pt);
  const ahead = bez(Math.min(1, pt + 0.02));
  const ang = (Math.atan2(ahead.y - here.y, ahead.x - here.x) * 180) / Math.PI;
  const notif = pop(f, 58, 18);
  const emailIn = ramp(f, 78, 88, (t) => t);
  return (
    <div style={{ position: "absolute", inset: 0, overflow: "hidden", fontFamily: FONT }}>
      <Bg color={BG.s6} glow="#2CD3A8" gx={1450} gy={450} />
      <Giant f={f} text="15" x={900} y={560} color="rgba(255,255,255,0.12)" size={700} drift={-0.5} />
      <RiseLines f={f} at={2} x={96} y={64} size={86} lines={[{ t: "Dosen telat menilai?", color: "#052016" }, { t: "Diingatkan otomatis.", color: C.white }]} />

      <Cut name="mon-panel0" x={px} y={py} s={ps} radius={14} ty={(1 - ramp(f, 4, 22)) * 50} opacity={ramp(f, 4, 12, (t) => t) * (1 - checked)} />
      <Cut name="mon-panel1" x={px} y={py} s={ps} radius={14} ty={(1 - ramp(f, 4, 22)) * 50} opacity={ramp(f, 4, 12, (t) => t) * checked}>
        <Hi r={{ x: send.x, y: send.y, w: send.w, h: send.h }} p={ramp(f, 30, 38) * (1 - ramp(f, 46, 52, (t) => t))} radius={8} pad={3} w={3} color={C.ink} />
      </Cut>
      <Burst f={f} at={14} x={cb0.x} y={cb0.y} color="#052016" max={90} />
      <Burst f={f} at={20} x={cb1.x} y={cb1.y} color="#052016" max={90} />
      <Burst f={f} at={44} x={sx} y={sy} color={C.white} max={150} />
      <Cursor f={f} way={[{ f: 6, x: 700, y: 700 }, { f: 13, x: cb0.x, y: cb0.y }, { f: 19, x: cb1.x, y: cb1.y }, { f: 38, x: sx, y: sy }, { f: 64, x: sx + 100, y: sy + 120 }]} pressAt={44} opacity={1 - ramp(f, 64, 72, (t) => t)} />

      <Cut name="log-panel" x={px} y={620} s={ps} radius={14} k={logIn} opacity={ramp(f, 48, 54, (t) => t)} ty={(1 - clamp01(logIn)) * 80}>
        <Hi r={{ x: 885, y: 150, w: 106, h: 194 }} p={ramp(f, 66, 76) * (1 - ramp(f, 100, 108, (t) => t))} radius={10} pad={2} w={3} />
      </Cut>

      {f >= 42 && f < 68 && (
        <div style={{ position: "absolute", left: here.x - 32, top: here.y - 32, transform: `rotate(${ang}deg) scale(${1 - ramp(f, 58, 68, (t) => t) * 0.5})`, zIndex: 30, filter: "drop-shadow(0 10px 16px rgba(0,0,0,0.3))" }}>
          <div style={{ width: 64, height: 64, borderRadius: 32, background: C.white, display: "flex", alignItems: "center", justifyContent: "center" }}>
            <Plane s={38} />
          </div>
        </div>
      )}

      <Phone3D x={1330} y={130} h={800} ry={lerp(20, -9, phIn)} rx={lerp(10, 4, phIn)} ty={(1 - clamp01(phIn)) * 600} opacity={clamp01(phIn * 3)}>
        <div style={{ position: "absolute", inset: 0, background: "#F5F5F7" }}>
          <div style={{ position: "absolute", inset: 0, opacity: emailIn }}>
            <EmailScreen f={f} at={80} />
          </div>
          <div
            style={{
              position: "absolute",
              left: 14,
              right: 14,
              top: 8,
              height: 84,
              borderRadius: 22,
              background: "rgba(255,255,255,0.97)",
              boxShadow: "0 12px 30px rgba(0,0,0,0.25)",
              transform: `translateY(${(1 - clamp01(notif)) * -120}px)`,
              opacity: (1 - emailIn) * clamp01(notif * 2),
              display: "flex",
              alignItems: "center",
              gap: 12,
              padding: "0 16px",
              fontFamily: FONT,
            }}
          >
            <div style={{ width: 46, height: 46, borderRadius: 12, background: C.white, border: "1.5px solid #DDE3DF", display: "flex", alignItems: "center", justifyContent: "center" }}>
              <Img src={staticFile("brand/telu-mark.png")} style={{ height: 30 }} />
            </div>
            <div>
              <div style={{ fontSize: 15, fontWeight: 700, color: C.ink }}>Capstone FRI</div>
              <div style={{ fontSize: 14, color: "#4B5563" }}>[Capstone] Pengisian nilai</div>
            </div>
          </div>
        </div>
      </Phone3D>
    </div>
  );
};

/* ===================================================================== S7 */

export const S7: React.FC = () => {
  const f = useCurrentFrame();
  const s = 1.5;
  const x = 154;
  const y0 = 340;
  const crop = (h: number): { x: number; y: number; w: number; h: number } => ({ x: 0, y: 0, w: 1092, h });
  const hs = [size("rec-thead").h, size("rec-row0").h, size("rec-row1").h, size("rec-row2").h];
  let acc = y0;
  const ys = hs.map((h) => {
    const cur = acc;
    acc += h * s;
    return cur;
  });
  const names = ["rec-thead", "rec-row0", "rec-row1", "rec-row2"];
  const tableH = acc - y0;
  const press = interpolate(f, [38, 40, 45], [0, 1, 0], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  const chip = pop(f, 46, 18);
  return (
    <div style={{ position: "absolute", inset: 0, overflow: "hidden", fontFamily: FONT }}>
      <Bg color={BG.s7} glow="#DDEBE2" gx={960} gy={300} />
      <Giant f={f} text="16" x={1000} y={540} color="rgba(0,132,61,0.07)" size={700} drift={-0.5} />
      <RiseLines f={f} at={2} x={0} y={70} size={110} width={1920} align="center" lines={[{ t: "Rekap nilai otomatis." }]} />
      <div style={{ position: "absolute", left: x - 8, top: y0 - 8, width: 1092 * s + 16, height: tableH + 16, borderRadius: 22, background: C.white, boxShadow: "0 30px 80px rgba(0,0,0,0.16)", opacity: ramp(f, 4, 12, (t) => t), transform: `scaleY(${ramp(f, 4, 16)})`, transformOrigin: "50% 0" }} />
      {names.map((n, i) => (
        <Cut key={n} name={n} x={x} y={ys[i]} s={s} clip={crop(hs[i])} shadow={false} tx={(1 - ramp(f, 8 + i * 4, 22 + i * 4)) * 240} opacity={ramp(f, 8 + i * 4, 14 + i * 4, (t) => t)} />
      ))}
      <div
        style={{
          position: "absolute",
          left: x + 1004 * s,
          top: y0 - 4,
          width: 84 * s,
          height: tableH + 8,
          borderRadius: 14,
          border: `4px solid ${C.accent}`,
          background: "rgba(0,179,136,0.14)",
          boxShadow: `0 0 28px ${C.accent}88`,
          opacity: pulseRing(f, 30, 38, 120, 130),
          transform: `scale(${1 + (1 - ramp(f, 30, 40)) * 0.06})`,
        }}
      />
      <Cut name="rec-btn-igadis" x={154} y={720} s={2.2} radius={8} k={pop(f, 24, 16) * (1 - press * 0.08)} opacity={ramp(f, 24, 30, (t) => t)}>
        <Hi r={{ x: 0, y: 0, w: 160, h: 44 }} p={ramp(f, 30, 38)} radius={8} pad={3} w={2} />
      </Cut>
      <Burst f={f} at={40} x={WIPE.w7.x} y={WIPE.w7.y} color={C.brand} max={200} />
      <Cursor f={f} way={[{ f: 24, x: 900, y: 880 }, { f: 38, x: WIPE.w7.x, y: WIPE.w7.y }]} pressAt={40} opacity={1 - ramp(f, 50, 58, (t) => t)} />
      <div
        style={{
          position: "absolute",
          left: 560,
          top: 726,
          height: 86,
          padding: "0 30px 0 18px",
          borderRadius: 18,
          background: C.ink,
          color: C.white,
          display: "flex",
          alignItems: "center",
          gap: 18,
          fontSize: 28,
          fontWeight: 600,
          transform: `translateX(${(1 - clamp01(chip)) * -160}px) scale(${clamp01(chip)})`,
          transformOrigin: "0 50%",
          opacity: clamp01(chip * 2),
          boxShadow: "0 24px 60px rgba(0,0,0,0.3)",
        }}
      >
        <div style={{ width: 52, height: 52, borderRadius: 12, background: C.brand, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 17, fontWeight: 800, letterSpacing: "0.04em" }}>XLSX</div>
        nilai-capstone-igadis-20261111-093000.xlsx
      </div>
    </div>
  );
};

/* ===================================================================== S8 */

const TEAM = [
  { name: "Rizki", img: "team/rizky_cut.png", w: 508, h: 784, cx: 548, top: 496, hh: 584, disc: C.brand, dd: 350, dy: 100, at: 74, rot: -3 },
  { name: "Via", img: "team/via_cut.png", w: 798, h: 1264, cx: 960, top: 436, hh: 650, disc: C.accent, dd: 400, dy: 135, at: 64, rot: 0 },
  { name: "Jay", img: "team/jay_cut.png", w: 757, h: 1201, cx: 1372, top: 500, hh: 590, disc: "#CFF3E2", dd: 360, dy: 122, at: 84, rot: 3 },
];

const SPARKS = Array.from({ length: 14 }, (_, i) => ({
  x: 140 + ((i * 397) % 1640),
  y: 330 + ((i * 211) % 640),
  d: 10 + ((i * 7) % 16),
  ph: i * 1.7,
  plus: i % 3 === 0,
}));

export const S8: React.FC = () => {
  const f = useCurrentFrame();
  const t1 = ramp(f, 4, 24);
  const t2 = ramp(f, 14, 34);
  const t3 = ramp(f, 24, 44);
  const k = ramp(f, 52, 78, EASE_IO);
  const mix = (a: number, b: number) => lerp(a, b, k);
  const c1 = ramp(f, 62, 80);
  const name = "Du Bist Gut Genug";
  const sway = (ph: number, a = 6) => Math.sin(f / 18 + ph) * a;
  return (
    <div style={{ position: "absolute", inset: 0, overflow: "hidden", fontFamily: FONT, textAlign: "center" }}>
      <Bg color={BG.s8} glow={C.stageLift} gx={960} gy={620} />

      {/* teks raksasa kontur di belakang para figur (kedalaman) */}
      <div style={{ position: "absolute", left: 0, right: 0, top: 330, opacity: ramp(f, 58, 90) * 0.9, transform: `translateX(${(f - 60) * -0.35}px)`, fontSize: 300, lineHeight: 0.92, fontWeight: 800, letterSpacing: "-0.05em", color: "transparent", WebkitTextStroke: "2px rgba(255,255,255,0.16)", whiteSpace: "nowrap" }}>
        <div>DU BIST</div>
        <div style={{ transform: "translateX(120px)" }}>GUT GENUG</div>
      </div>

      {/* percikan dekoratif */}
      {SPARKS.map((sp, i) => {
        const a = ramp(f, 70 + i * 2, 90 + i * 2) * (0.35 + 0.35 * Math.sin(f / 10 + sp.ph));
        return sp.plus ? (
          <div key={i} style={{ position: "absolute", left: sp.x, top: sp.y + Math.sin(f / 22 + sp.ph) * 14, width: sp.d * 1.6, height: sp.d * 1.6, opacity: a, color: C.accent, fontSize: sp.d * 2, fontWeight: 800, lineHeight: 1 }}>+</div>
        ) : (
          <div key={i} style={{ position: "absolute", left: sp.x, top: sp.y + Math.sin(f / 22 + sp.ph) * 14, width: sp.d, height: sp.d, borderRadius: "50%", background: i % 2 ? C.accent : C.white, opacity: a }} />
        );
      })}

      {/* kunci merek */}
      <div style={{ position: "absolute", top: mix(250, 30), width: "100%", fontSize: mix(168, 84), fontWeight: 800, letterSpacing: "-0.045em", lineHeight: 1.1, color: C.white, opacity: t1, transform: `translateY(${(1 - t1) * 34}px)` }}>Capstone</div>
      <div style={{ position: "absolute", top: mix(470, 190), width: "100%", fontSize: 34, fontWeight: 500, color: C.softWhite, opacity: t2 * (1 - k), transform: `translateY(${(1 - t2) * 20}px)` }}>
        Capstone Design &amp; Project · Fakultas Rekayasa Industri
      </div>
      <div style={{ position: "absolute", top: mix(560, 128), left: 0, right: 0, display: "flex", justifyContent: "center", opacity: t2, transform: `translateY(${(1 - t2) * 20}px) scale(${mix(1, 0.5)})`, transformOrigin: "50% 0" }}>
        <div style={{ display: "flex", alignItems: "center", gap: 36, background: C.white, padding: "22px 40px", borderRadius: 20, boxShadow: "0 24px 70px rgba(0,0,0,0.4)" }}>
          <Img src={staticFile("brand/telu-horizontal.png")} style={{ height: 64 }} />
          <div style={{ width: 2, height: 56, background: "#D8DDD9" }} />
          <Img src={staticFile("brand/fri-horizontal.png")} style={{ height: 64 }} />
        </div>
      </div>
      <div style={{ position: "absolute", top: 740, width: "100%", fontSize: 46, fontWeight: 700, letterSpacing: "-0.01em", color: C.accent, opacity: t3 * (1 - k), transform: `translateY(${(1 - t3) * 20}px)` }}>16 minggu. Satu alur.</div>

      {/* judul kredit */}
      <div style={{ position: "absolute", top: 236, width: "100%", fontSize: 22, fontWeight: 600, letterSpacing: "0.36em", color: C.faintWhite, opacity: c1 }}>CREATED BY</div>
      <div style={{ position: "absolute", top: 268, width: "100%", fontSize: 104, fontWeight: 800, letterSpacing: "-0.04em", color: C.white, lineHeight: 1.05 }}>
        {name.split("").map((ch, i) => {
          const p = ramp(f, 66 + i * 1.5, 66 + i * 1.5 + 14);
          return (
            <span key={i} style={{ display: "inline-block", whiteSpace: "pre", transform: `translateY(${(1 - p) * 70}px)`, opacity: clamp01(p * 2) }}>
              {ch}
            </span>
          );
        })}
      </div>

      {/* tim: potongan foto tanpa latar di atas cakram warna */}
      {TEAM.map((p, i) => {
        const dIn = pop(f, p.at - 8, 18);
        const fIn = pop(f, p.at, 24);
        const w = (p.hh * p.w) / p.h;
        const bob = sway(i * 2);
        return (
          <React.Fragment key={p.name}>
            <div
              style={{
                position: "absolute",
                left: p.cx - p.dd / 2,
                top: p.top + p.dy - p.dd / 2 + bob * 0.5,
                width: p.dd,
                height: p.dd,
                borderRadius: "50%",
                background: p.disc,
                transform: `scale(${clamp01(dIn) * (1 + Math.sin(f / 14 + i) * 0.02)})`,
                boxShadow: `0 0 0 10px ${p.disc}33, 0 30px 80px rgba(0,0,0,0.35)`,
                opacity: clamp01(dIn * 2),
              }}
            />
            <div
              style={{
                position: "absolute",
                left: p.cx - p.dd / 2 - 26,
                top: p.top + p.dy - p.dd / 2 - 26 + bob * 0.5,
                width: p.dd + 52,
                height: p.dd + 52,
                borderRadius: "50%",
                border: "2px dashed rgba(255,255,255,0.35)",
                transform: `rotate(${f * (i % 2 ? -0.6 : 0.6)}deg) scale(${clamp01(dIn)})`,
                opacity: clamp01(dIn * 2) * 0.8,
              }}
            />
            <Img
              src={staticFile(p.img)}
              style={{
                position: "absolute",
                left: p.cx - w / 2,
                top: p.top,
                width: w,
                height: p.hh,
                transform: `translateY(${(1 - clamp01(fIn)) * 760 + bob}px) rotate(${p.rot * (1 - clamp01(fIn) * 0.5)}deg) scale(${0.9 + 0.1 * fIn})`,
                transformOrigin: "50% 100%",
                opacity: clamp01(fIn * 3),
                filter: "drop-shadow(0 20px 36px rgba(0,0,0,0.45))",
              }}
            />
            <div
              style={{
                position: "absolute",
                left: p.cx - 110,
                top: 958,
                width: 220,
                textAlign: "center",
                transform: `translateY(${(1 - clamp01(pop(f, p.at + 18, 16))) * 60}px) scale(${pop(f, p.at + 18, 16)})`,
                opacity: clamp01(pop(f, p.at + 18, 16) * 2),
              }}
            >
              <span style={{ display: "inline-block", padding: "10px 30px", borderRadius: 999, background: C.white, color: C.ink, fontSize: 36, fontWeight: 800, letterSpacing: "-0.01em", boxShadow: "0 14px 34px rgba(0,0,0,0.35)" }}>{p.name}</span>
            </div>
          </React.Fragment>
        );
      })}
    </div>
  );
};
