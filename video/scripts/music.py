"""Underscore sintetis 120 BPM (1 ketukan = 15 frame @30fps) yang diselaraskan dengan adegan.

Pergantian adegan jatuh tepat di ketukan (frame kelipatan 15), jadi irama, build-up, breakdown,
dan resolusi musik mengikuti cerita:
  ketukan  0– 6  intro        : riser + pad membesar
  ketukan  6–15  admin        : groove tipis (kick lembut, bass, arpeggio)
  ketukan 15–21  mahasiswa    : + clap, hi-hat
  ketukan 21–29  bimbingan    : + melodi
  ketukan 29–37  rubrik       : breakdown (tanpa kick), pad & denting jarang, riser naik
  ketukan 37–50  email/rekap  : drop penuh (kick, bass, clap, hat 16, arpeggio, melodi)
  ketukan 50–60  penutup      : resolusi — pad hangat & bel, kick berhenti
Progresi akor per bar (4 ketukan): Am – F – C – G.
"""
import numpy as np

BPM = 120
BEAT = 60 / BPM
CHORDS = [(45, [57, 60, 64]), (41, [53, 57, 60]), (48, [55, 60, 64]), (43, [55, 59, 62])]  # (bass, nada akor)
LEAD = [[76, 79, 81, 79], [77, 81, 84, 81], [76, 79, 84, 79], [79, 83, 86, 83]]


def hz(m):
    return 440.0 * 2 ** ((m - 69) / 12)


def render_music(N, SR, seed=11):
    rng = np.random.default_rng(seed)
    n_beats = int(N / SR / BEAT) + 1

    def ex(n, k):
        return np.exp(-np.arange(n) / SR * k)

    def ma(x, n):  # low-pass kasar (rata-rata bergerak, vektor)
        c = np.cumsum(np.insert(x, 0, 0.0))
        y = (c[n:] - c[:-n]) / n
        return np.concatenate([np.full(n - 1, y[0]), y])

    def hp(x, n):
        return x - ma(x, n)

    def place(bus, sig, t0, g=1.0):
        o = int(t0 * SR)
        if o >= len(bus) or o < -len(sig):
            return
        a = max(0, -o)
        e = min(len(sig), len(bus) - o)
        if e > a:
            bus[o + a:o + e] += sig[a:e] * g

    def tri_harm(f, d, harm=(1, 2, 3, 4), amp=(1, .5, .25, .12)):
        t = np.arange(int(d * SR)) / SR
        return sum(a * np.sin(2 * np.pi * f * h * t) for h, a in zip(harm, amp))

    # ---- suara ----
    def kick():
        d = 0.42
        t = np.arange(int(d * SR)) / SR
        f = 48 + 110 * np.exp(-t * 26)
        s = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t * 7.5)
        s += 0.25 * hp(rng.standard_normal(len(t)), 6) * np.exp(-t * 90)
        return s

    def clap():
        d = 0.22
        n = int(d * SR)
        t = np.arange(n) / SR
        x = hp(rng.standard_normal(n), 14) * 0.0
        for off in (0, .012, .024):
            seg = hp(rng.standard_normal(n), 10) * np.exp(-np.maximum(0, t - off) * 55)
            seg[t < off] = 0
            x += seg
        return x * 0.5

    def hat(open_=False):
        d = 0.18 if open_ else 0.05
        n = int(d * SR)
        return hp(rng.standard_normal(n), 5) * ex(n, 22 if open_ else 90)

    def crash():
        n = int(1.8 * SR)
        return hp(rng.standard_normal(n), 4) * ex(n, 2.6)

    def bass(m, d=0.46):
        n = int(d * SR)
        t = np.arange(n) / SR
        return (np.sin(2 * np.pi * hz(m) * t) + 0.35 * np.sin(2 * np.pi * hz(m) * 2 * t)) * ex(n, 5.5) * (1 - np.exp(-t * 400))

    def pluck(m, d=0.5, g=1.0):
        return tri_harm(hz(m), d, (1, 2, 3), (1, .3, .12)) * ex(int(d * SR), 7) * g

    def lead_note(m, d=0.42):
        n = int(d * SR)
        t = np.arange(n) / SR
        vib = 1 + 0.004 * np.sin(2 * np.pi * 5.5 * t) * np.minimum(1, t * 4)
        s = np.sin(2 * np.pi * hz(m) * t * vib) + 0.3 * np.sin(2 * np.pi * hz(m) * 2 * t)
        return s * (1 - np.exp(-t * 60)) * ex(n, 4.5)

    def bell(m, d=1.6):
        n = int(d * SR)
        t = np.arange(n) / SR
        s = np.sin(2 * np.pi * hz(m) * t) + 0.5 * np.sin(2 * np.pi * hz(m) * 2.76 * t) * np.exp(-t * 5) + 0.25 * np.sin(2 * np.pi * hz(m) * 5.4 * t) * np.exp(-t * 9)
        return s * ex(n, 2.2)

    def pad_chord(notes, d=2.7):
        n = int(d * SR)
        t = np.arange(n) / SR
        x = np.zeros(n)
        for m in notes:
            for det in (-0.07, 0.0, 0.07):
                f0 = hz(m) * 2 ** (det / 12)
                x += sum(np.sin(2 * np.pi * f0 * h * t + h) / h for h in range(1, 7))
        env = np.minimum(1, t / 0.45) * np.minimum(1, (d - t) / 0.6)
        return ma(x * env, 10) / (len(notes) * 3)

    def riser(d):
        n = int(d * SR)
        t = np.arange(n) / SR
        x = hp(rng.standard_normal(n), 3) * (t / d) ** 2.2
        return x * 0.6

    # ---- tabel level per bagian (ketukan) ----
    def lv(table, b):
        for a, e, g in table:
            if a <= b < e:
                return g
        return 0.0

    KICK = [(6, 15, .55), (15, 29, .8), (37, 50, 1.0)]
    HAT = [(6, 15, .25), (15, 29, .45), (37, 50, .5)]
    CLAP = [(15, 29, .55), (37, 50, .8)]
    BASS = [(6, 29, .8), (37, 50, 1.0)]
    ARP = [(6, 15, .5), (15, 29, .6), (29, 37, .45), (37, 50, .7)]
    LEADT = [(21, 29, .5), (37, 50, .6)]
    PAD = [(0, 6, .55), (6, 15, .45), (15, 29, .5), (29, 37, .85), (37, 50, .45), (50, 61, .9)]

    kick_b = np.zeros(N)
    drums = np.zeros(N)
    bass_b = np.zeros(N)
    arp_b = np.zeros(N)
    lead_b = np.zeros(N)
    pad_b = np.zeros(N)
    fx_b = np.zeros(N)

    K = kick()
    kick_times = []
    # intro: dentuman kick pertama + snare roll 16-an
    place(drums, K, 0.0, 0.9)
    place(fx_b, crash(), 0.0, 0.25)
    for j in range(24):  # ketukan 3–6, hi-hat 16-an menanjak
        tt = 3 * BEAT + j * BEAT / 8
        place(drums, hat(), tt, 0.12 + 0.35 * j / 24)
    place(fx_b, riser(2.0), 2 * BEAT, 0.5)

    for b in range(n_beats):
        t0 = b * BEAT
        ci = (b // 4) % 4
        bass_m, notes = CHORDS[ci]
        g = lv(KICK, b)
        if g:
            place(kick_b, K, t0, g)
            kick_times.append(t0)
        gh = lv(HAT, b)
        if gh:
            fast = 37 <= b < 50
            steps = 4 if fast else 2
            for k in range(steps):
                if not fast and k == 0 and b < 15:
                    continue  # hat jarang di bagian awal
                place(drums, hat(open_=(k == steps // 2 and b % 2 == 1 and not fast)), t0 + k * BEAT / steps + (BEAT / 4 if not fast and b < 15 else 0), gh * (0.7 if k % 2 else 1.0))
        gc = lv(CLAP, b)
        if gc and b % 2 == 1:
            place(drums, clap(), t0, gc)
        gb = lv(BASS, b)
        if gb:
            place(bass_b, bass(bass_m), t0, gb)
            if b % 2 == 1:
                place(bass_b, bass(bass_m + 12, 0.25), t0 + BEAT * 0.5, gb * 0.5)
        ga = lv(ARP, b)
        if ga:
            sparse = 29 <= b < 37
            pat = [0, 1, 2, 1] if sparse else [0, 1, 2, 1, 0, 1, 2, 1]
            step = BEAT if sparse else BEAT / 2
            per = 1 if sparse else 2
            for k in range(per):
                m = notes[pat[(b * per + k) % len(pat)]] + 12
                place(arp_b, pluck(m), t0 + k * step, ga)
        gl = lv(LEADT, b)
        if gl:
            motif = LEAD[ci]
            q = b % 4
            place(lead_b, lead_note(motif[q], 0.9 if q in (0, 2) else 0.42), t0 + (0.0 if q in (0, 2) else BEAT * 0.5), gl)
        # pad: satu akor per bar
        if b % 4 == 0:
            gp = lv(PAD, b)
            if gp:
                place(pad_b, pad_chord([bass_m + 12] + notes), t0 - 0.05, gp)
        # bel penutup (resolusi)
        if 50 <= b < 60 and b % 2 == 0:
            place(arp_b, bell(notes[(b // 2) % 3] + 24), t0, 0.5)

    # build-up & crash
    place(fx_b, riser(2.0), 33 * BEAT, 0.9)
    for cb in (37, 45, 50):
        place(fx_b, crash(), cb * BEAT, 0.5 if cb != 50 else 0.35)
    place(drums, K, 50 * BEAT, 1.0)  # dentuman terakhir di awal penutup
    kick_times.append(50 * BEAT)

    # sidechain: pad & arpeggio "bernapas" mengikuti kick
    sc = np.ones(N)
    rec = int(0.32 * SR)
    shape = 1 - 0.55 * np.exp(-np.arange(rec) / (0.09 * SR))
    for kt in kick_times:
        o = int(kt * SR)
        e = min(N, o + rec)
        sc[o:e] = np.minimum(sc[o:e], shape[: e - o])

    dry = kick_b + drums * 0.9 + bass_b * 0.85 + (arp_b * 0.55 + pad_b * 0.5 + lead_b * 0.5) * sc
    # reverb ringan (IR peluruhan eksponensial)
    L = int(1.5 * SR)
    ir = rng.standard_normal(L) * np.exp(-np.arange(L) / SR * 3.2)
    ir = ma(ir, 4)
    size = 1 << int(np.ceil(np.log2(N + L)))
    wet_src = (arp_b * 0.6 + pad_b * 0.5 + lead_b + drums * 0.15)
    wet = np.fft.irfft(np.fft.rfft(wet_src, size) * np.fft.rfft(ir, size), size)[:N] * 0.06
    mix = dry + wet + fx_b * 0.5

    # fade masuk/keluar
    tt = np.arange(N) / SR
    total = N / SR
    mix *= np.minimum(1, tt / 0.15) * np.clip((total - 0.1 - tt) / 2.6, 0, 1)
    # normalisasi: RMS ≈ -21 dBFS agar SFX & VO tetap di depan
    rms = np.sqrt(np.mean(mix ** 2)) + 1e-9
    mix *= (10 ** (-21 / 20)) / rms
    return mix
