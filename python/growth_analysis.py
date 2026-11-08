#!/usr/bin/env python3
"""Analisis pertumbuhan maggot BSF. Hanya library standar.
Input : JSON dari stdin  {"batches":[{"id","code","name","logs":[{"age","weight","length"}]}]}
Output: JSON ke stdout.
Tidak ada prediksi panen; estimasi hanya ekstrapolasi linear bila data cukup."""
import json, math, sys

MAX_LOGS = 5000

def regress(xs, ys):
    n = len(xs)
    mx, my = sum(xs) / n, sum(ys) / n
    sxx = sum((x - mx) ** 2 for x in xs)
    if sxx == 0:
        return None
    sxy = sum((x - mx) * (y - my) for x, y in zip(xs, ys))
    slope = sxy / sxx
    icpt = my - slope * mx
    ss_tot = sum((y - my) ** 2 for y in ys)
    ss_res = sum((y - (icpt + slope * x)) ** 2 for x, y in zip(xs, ys))
    r2 = 1 - ss_res / ss_tot if ss_tot > 0 else 0.0
    return slope, icpt, r2

def clean(logs):
    out = []
    for l in logs[:MAX_LOGS]:
        try:
            a, w = int(l["age"]), float(l["weight"])
        except (KeyError, TypeError, ValueError):
            continue
        if 0 <= a <= 400 and math.isfinite(w) and w > 0:
            out.append((a, w))
    out.sort()
    return out

def analyze(b):
    pts = clean(b.get("logs", []))
    r = {"id": b.get("id"), "code": str(b.get("code", "")), "name": str(b.get("name", "")),
         "n_logs": len(pts), "steps": [], "projection": None}
    if len(pts) < 2:
        r.update(trend="data belum cukup", note="Minimal 2 pengukuran diperlukan.")
        return r
    ages = [p[0] for p in pts]; ws = [p[1] for p in pts]
    r["first_weight"], r["last_weight"] = ws[0], ws[-1]
    r["change_g"] = round(ws[-1] - ws[0], 3)
    r["change_pct"] = round((ws[-1] - ws[0]) / ws[0] * 100, 1)
    for i in range(1, len(pts)):
        dd = ages[i] - ages[i - 1]
        r["steps"].append({"from_age": ages[i - 1], "to_age": ages[i],
                           "delta_g": round(ws[i] - ws[i - 1], 3),
                           "per_day": round((ws[i] - ws[i - 1]) / dd, 4) if dd > 0 else None})
    r["trend"] = "naik" if r["change_pct"] > 5 else "turun" if r["change_pct"] < -5 else "stabil"
    fit = regress([float(a) for a in ages], ws)
    if fit is None:
        r["note"] = "Semua pengukuran pada umur yang sama; laju harian tidak dapat dihitung."
        return r
    slope, icpt, r2 = fit
    r["slope_g_per_day"], r["r2"] = round(slope, 4), round(r2, 3)
    if len(pts) >= 4 and len(set(ages)) >= 3 and ages[-1] - ages[0] >= 3 and r2 >= 0.6 and slope > 0:
        r["projection"] = {"method": "regresi linear",
                           "days_3": round(icpt + slope * (ages[-1] + 3), 3),
                           "days_7": round(icpt + slope * (ages[-1] + 7), 3),
                           "note": "Estimasi berat sampel berdasarkan tren historis, bukan prediksi panen."}
    else:
        r["note"] = "Data belum cukup/konsisten untuk estimasi (butuh >=4 pengukuran, >=3 umur berbeda, R2>=0.6)."
    return r

def main():
    try:
        data = json.loads(sys.stdin.read())
        batches = data["batches"]
        if not isinstance(batches, list) or len(batches) > 500:
            raise ValueError
        res = [analyze(b) for b in batches if isinstance(b, dict)]
    except Exception:
        print(json.dumps({"ok": False}))
        return 1
    ranked = sorted([x for x in res if "slope_g_per_day" in x], key=lambda x: x["slope_g_per_day"], reverse=True)
    comp = [{"rank": i + 1, "id": x["id"], "code": x["code"], "slope_g_per_day": x["slope_g_per_day"]}
            for i, x in enumerate(ranked)]
    print(json.dumps({"ok": True, "batches": res, "ranking": comp}))
    return 0

if __name__ == "__main__":
    sys.exit(main())
