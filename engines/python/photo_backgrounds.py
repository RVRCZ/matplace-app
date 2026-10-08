#!/usr/bin/env python3
"""
The backdrops of the product photo tool (session 4, /tools/photo), drawn here rather than photographed, so there is
nothing to license: wood (grain from stretched noise), marble (veins from thresholded blurred noise), concrete (grey
noise with pores), paper (fine fibre noise), linen (a woven pattern). 1200 × 1200 JPEG; the page stretches them.

  photo_backgrounds.py <public/img/backgrounds>
"""
import os
import random
import sys

from PIL import Image, ImageChops, ImageDraw, ImageEnhance, ImageFilter

S = 1200
random.seed(7)


def noise(size, blur=0):
    im = Image.effect_noise(size, 64).convert("L")
    return im.filter(ImageFilter.GaussianBlur(blur)) if blur else im


def tint(gray, dark, light):
    """A grey picture coloured between two colours."""
    out = Image.new("RGB", gray.size)
    px = gray.load()
    o = out.load()
    for y in range(gray.height):
        for x in range(gray.width):
            k = px[x, y] / 255.0
            o[x, y] = tuple(int(dark[i] + (light[i] - dark[i]) * k) for i in range(3))
    return out


def wood():
    # grain: noise stretched along x, a few darker streaks, a warm oak tint
    g = noise((S // 40, S), 0).resize((S, S), Image.BICUBIC)
    g = g.filter(ImageFilter.GaussianBlur(1.2))
    streaks = Image.new("L", (S, S), 128)
    d = ImageDraw.Draw(streaks)
    for _ in range(26):
        y = random.randint(0, S)
        d.line([(0, y), (S, y + random.randint(-30, 30))], fill=random.randint(70, 110), width=random.randint(2, 7))
    streaks = streaks.filter(ImageFilter.GaussianBlur(6))
    g = ImageChops.multiply(g, ImageEnhance.Contrast(streaks).enhance(1.4))
    g = ImageEnhance.Contrast(g).enhance(0.9)
    return tint(g, (134, 96, 62), (214, 182, 140))


def marble():
    # veins: large soft noise, the values near the middle become thin dark lines; a faint cloudiness behind them
    g = noise((S // 30, S // 30)).resize((S, S), Image.BICUBIC).filter(ImageFilter.GaussianBlur(18))
    veins = g.point(lambda v: 255 - min(255, abs(v - 128) * 26))
    veins = veins.filter(ImageFilter.GaussianBlur(1.6)).point(lambda v: int(v * 0.42))
    fine = noise((S // 10, S // 10)).resize((S, S), Image.BICUBIC).filter(ImageFilter.GaussianBlur(8))
    fine = fine.point(lambda v: 255 - min(255, abs(v - 128) * 30)).filter(ImageFilter.GaussianBlur(1)).point(lambda v: int(v * 0.08))
    base = noise((S // 3, S // 3)).resize((S, S), Image.BICUBIC).filter(ImageFilter.GaussianBlur(3)).point(lambda v: 232 + (v - 128) // 16)
    g = ImageChops.subtract(ImageChops.subtract(base, veins), fine)
    return tint(g, (140, 140, 150), (250, 249, 247))


def concrete():
    g = noise((S, S)).filter(ImageFilter.GaussianBlur(0.8))
    coarse = noise((S // 8, S // 8)).resize((S, S), Image.BICUBIC).filter(ImageFilter.GaussianBlur(6))
    g = Image.blend(g, coarse, 0.6)
    d = ImageDraw.Draw(g)
    for _ in range(900):
        x, y, r = random.randint(0, S), random.randint(0, S), random.randint(1, 4)
        d.ellipse([x - r, y - r, x + r, y + r], fill=random.randint(60, 110))
    g = g.filter(ImageFilter.GaussianBlur(0.6))
    return tint(g, (150, 150, 150), (205, 204, 202))


def paper():
    g = noise((S, S)).filter(ImageFilter.GaussianBlur(0.9))
    g = ImageEnhance.Contrast(g).enhance(0.35)
    return tint(g, (228, 222, 210), (250, 247, 240))


def linen():
    warp = Image.new("L", (S, S), 200)
    px = warp.load()
    for y in range(S):
        for x in range(S):
            v = 190 + (18 if (x // 3 + y // 3) % 2 else -14) + ((x * 7 + y * 13) % 11) - 5
            px[x, y] = max(0, min(255, v))
    warp = warp.filter(ImageFilter.GaussianBlur(0.6))
    return tint(warp, (196, 188, 172), (236, 230, 218))


DRAW = {"wood": wood, "marble": marble, "concrete": concrete, "paper": paper, "linen": linen}


def main(argv):
    out = argv[1]
    for key in argv[2:] or list(DRAW):
        im = DRAW[key]()
        path = os.path.join(out, key + ".jpg")
        im.save(path, "JPEG", quality=80, optimize=True)
        print(key, os.path.getsize(path) // 1024, "kB")


if __name__ == "__main__":
    main(sys.argv)
