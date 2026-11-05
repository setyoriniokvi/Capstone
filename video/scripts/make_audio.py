#!/usr/bin/env python3
"""Sintesis SFX + underscore sederhana -> public/audio/bumper.wav (30 s, 48 kHz, stereo).
Cue dibaca dari src/timeline.json (sumber yang sama dengan Remotion)."""
import json, wave, pathlib
import numpy as np

ROOT = pathlib.Path(__file__).resolve().parent.parent
TL = json.loads((ROOT / "src/timeline.json").read_text())
SR, FPS = 48000, TL["fps"]
N = int(TL["total"] / FPS * SR)
rng = np.random.default_rng(7)

def t_(d): return np.arange(int(d * SR)) / SR
def env(x, a=0.005, r=0.1):
    n = len(x); e = np.ones(n)
    na, nr = max(1, int(a * SR)), max(1, int(r * SR))
    e[:na] = np.linspace(0, 1, na); e[-nr:] *= np.linspace(1, 0, nr)
    return x * e
def decay(d, k): return np.exp(-t_(d) * k)
def sine(f, d): return np.sin(2 * np.pi * f * t_(d))
def noise(d): return rng.standard_normal(int(d * SR))
def lp(x, fc):  # one-pole low-pass
    a = np.exp(-2 * np.pi * fc / SR); y = np.zeros_like(x); s = 0.0
    for i, v in enumerate(x): s = (1 - a) * v + a * s; y[i] = s
    return y
def hp(x, fc): return x - lp(x, fc)
def note(m): return 440 * 2 ** ((m - 69) / 12)

def tap():  return env(sine(1800, .05) * decay(.05, 70) * .5, .001, .03)
def tapAccent(): return env((sine(1200, .18) + .5 * sine(2400, .18)) * decay(.18, 18) * .5, .001, .1)
def tick(): return env(sine(2600, .04) * decay(.04, 90) * .45, .001, .02)
def tickErr(): return env(sine(330, .22) * decay(.22, 14) * .5 + sine(247, .22) * decay(.22, 14) * .3, .002, .1)
def click(): return env(hp(noise(.06), 1500) * decay(.06, 120) * .9 + sine(900, .06) * decay(.06, 60) * .3, .0005, .02)
def whoosh():
    d = .55; n = noise(d); out = np.zeros_like(n)
    for i, fc in enumerate(np.linspace(400, 5000, 6)):
        seg = lp(n, fc) - lp(n, fc * .5); out += seg
    sh = np.sin(np.pi * t_(d) / d) ** 2
    return env(out * sh * .22, .05, .2)
def swoosh(): return whoosh()[::1] * .7
def pop(): return env(sine(520, .12) * decay(.12, 26) * .6 + sine(1040, .12) * decay(.12, 40) * .2, .001, .06)
def beep(): return env(sine(1320, .1) * .35, .003, .04)
def beepLock(): return env((sine(1760, .16) * .35 + sine(2640, .16) * .15) * decay(.16, 8), .003, .08)
def scan():
    d = .9; tt = t_(d); f = 600 + 1400 * tt / d
    return env(np.sin(2 * np.pi * np.cumsum(f) / SR) * .12 * np.sin(np.pi * tt / d), .02, .15)
def sweep():
    d = 1.4; tt = t_(d); f = 300 * (6 ** (tt / d))
    return env(np.sin(2 * np.pi * np.cumsum(f) / SR) * .10 * (tt / d), .05, .2) + env(lp(noise(d), 3000) * .05 * (tt / d), .05, .2)
def hit():
    d = .9; return env(sine(55, d) * decay(d, 5) * .9 + lp(noise(d), 600) * decay(d, 9) * .4 + sine(110, d) * decay(d, 8) * .3, .001, .2)
def chime(m=84, g=.4):
    d = 1.1; return env((sine(note(m), d) + .4 * sine(note(m + 12), d) * decay(d, 4)) * decay(d, 3.2) * g, .002, .3)
def chimeUp():
    a = chime(84, .35); b = chime(91, .35); out = np.zeros(len(a) + int(.09 * SR)); out[:len(a)] += a; out[int(.09 * SR):] += b; return out
def arp():
    out = np.zeros(int(1.6 * SR))
    for i, m in enumerate([72, 76, 79, 84, 88]):
        n = chime(m, .28); o = int(i * .07 * SR); out[o:o + len(n)] += n[:len(out) - o]
    return out
def finalChord():
    d = 3.2; out = np.zeros(int(d * SR))
    for m, g in [(48, .5), (60, .3), (64, .28), (67, .28), (72, .22), (79, .14)]:
        s = (sine(note(m), d) + .3 * sine(note(m) * 2, d)) * g
        out += s * decay(d, 0.9)
    return env(out, .01, .8) * .5
def riser():
    d = 1.6; tt = t_(d); return env(lp(noise(d), 2500) * (tt / d) ** 2 * .1, .05, .1)

FX = dict(tap=tap, tapAccent=tapAccent, tick=tick, tickErr=tickErr, click=click, whoosh=whoosh, swoosh=swoosh,
          pop=pop, beep=beep, beepLock=beepLock, scan=scan, sweep=sweep, hit=hit, chime=chime, chimeUp=chimeUp,
          arp=arp, finalChord=finalChord, riser=riser)

mix = np.zeros(N)
for c in TL["cues"]:
    s = FX[c["t"]]() * c.get("g", 1.0)
    o = int(c["f"] / FPS * SR)
    e = min(N, o + len(s))
    if e > o: mix[o:e] += s[:e - o]

# Underscore: ~104 BPM, pad (Am-F-C-G) + pluk lembut, naik perlahan, hilang ke akhir
bpm = 104; beat = 60 / bpm; bed = np.zeros(N)
chords = [[57, 60, 64], [53, 57, 60], [48, 55, 60], [55, 59, 62]]
bar = beat * 4; nb = int(30 / bar) + 1
for b in range(nb):
    o = int(b * bar * SR); ch = chords[b % 4]; d = bar + .5; seg = np.zeros(int(d * SR))
    for m in ch:
        seg += (sine(note(m), d) + .25 * sine(note(m) * 2, d)) * .05
    seg = env(seg, .25, .5)
    e = min(N, o + len(seg)); bed[o:e] += seg[:e - o]
for i in range(int(30 / (beat / 2))):
    o = int(i * beat / 2 * SR); m = [chords[(i // 8) % 4][k % 3] + 12 for k in (0, 1, 2, 1)][i % 4]
    n = sine(note(m), .35) * decay(.35, 9) * .035
    e = min(N, o + len(n)); bed[o:e] += n[:e - o]
tt = np.arange(N) / SR
bed *= np.minimum(1, tt / 1.5) * np.where(tt > 26.5, np.clip((29.8 - tt) / 3.3, 0, 1), 1) * (0.7 + 0.3 * np.minimum(1, tt / 12))
mix += bed

mix = np.tanh(mix * 1.2) / np.tanh(1.2)
mix *= 0.89 / max(1e-6, np.abs(mix).max())
# stereo: lebarkan sedikit dengan jeda Haas
L = mix; R = np.concatenate([np.zeros(int(.0006 * SR)), mix[:-int(.0006 * SR)]])
pcm = (np.stack([L, R], 1) * 32767).astype("<i2")
out = ROOT / "public/audio/bumper.wav"
out.parent.mkdir(parents=True, exist_ok=True)
with wave.open(str(out), "wb") as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR); w.writeframes(pcm.tobytes())
print("OK", out, f"{N / SR:.2f}s")
