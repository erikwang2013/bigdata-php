#!/usr/bin/env python3
"""Generate Duoduo, the bigdata-php data-octopus pet, and its 12 expressions."""
from pathlib import Path
from string import Template

ROOT = Path(__file__).resolve().parent
OUT = ROOT / "pet-expressions"
OUT.mkdir(parents=True, exist_ok=True)

PET = Template('''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 360 380" width="360" height="380" role="img" aria-labelledby="petTitle petDesc">
  <title id="petTitle">$title</title>
  <desc id="petDesc">$desc</desc>
  <defs>
    <linearGradient id="bodyGrad" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#5EEAD4"/>
      <stop offset="1" stop-color="#0F766E"/>
    </linearGradient>
    <linearGradient id="scarfGrad" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#A78BFA"/>
      <stop offset="1" stop-color="#6D28D9"/>
    </linearGradient>
    <linearGradient id="badgeGrad" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#FDE68A"/>
      <stop offset="1" stop-color="#F59E0B"/>
    </linearGradient>
    <radialGradient id="glow">
      <stop offset="0" stop-color="#67E8F9" stop-opacity=".9"/>
      <stop offset="1" stop-color="#67E8F9" stop-opacity="0"/>
    </radialGradient>
    <style>
      .pet-bob { animation: petBob 5.2s ease-in-out infinite; transform-box: fill-box; transform-origin: 50% 100%; }
      .spark { animation: petSpark 2.6s ease-in-out infinite; }
      .tear { animation: petTear 2.2s ease-in infinite; }
      @keyframes petBob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-7px); } }
      @keyframes petSpark { 0%, 100% { opacity: .35; transform: scale(.8); } 50% { opacity: 1; transform: scale(1.08); } }
      @keyframes petTear { 0% { opacity: 0; transform: translateY(-4px); } 30% { opacity: 1; } 100% { opacity: 0; transform: translateY(18px); } }
      @media (prefers-reduced-motion: reduce) { .pet-bob, .spark, .tear { animation: none; } }
    </style>
  </defs>

  <g class="pet-bob">
    <ellipse cx="180" cy="352" rx="118" ry="15" fill="#0F172A" opacity=".14"/>

    <!-- data lake -->
    <path d="M40 342 Q90 326 140 342 T240 342 T340 342" fill="none" stroke="#38BDF8" stroke-width="6" stroke-linecap="round" opacity=".45"/>
    <path d="M56 354 Q106 338 156 354 T256 354 T336 354" fill="none" stroke="#0EA5E9" stroke-width="4" stroke-linecap="round" opacity=".3"/>

    <!-- floating data nodes -->
    <g opacity=".7" fill="none" stroke="#38BDF8" stroke-width="1.4" stroke-dasharray="3 5">
      <path d="M64 132 L104 164"/><path d="M296 126 L260 166"/><path d="M42 228 L84 238"/><path d="M318 232 L278 242"/>
    </g>
    <g class="spark">
      <circle cx="64" cy="132" r="5" fill="#38BDF8"/><circle cx="296" cy="126" r="4" fill="#A78BFA"/>
      <circle cx="42" cy="228" r="4" fill="#34D399"/><circle cx="318" cy="232" r="5" fill="#F472B6"/>
    </g>

    <!-- eight tentacles for the driver groups -->
    <g fill="none" stroke-linecap="round">
      <path d="M112 246 C82 264 62 294 66 336" stroke="#3B82F6" stroke-width="19"/>
      <path d="M128 264 C100 300 96 330 112 356" stroke="#F59E0B" stroke-width="19"/>
      <path d="M150 276 C136 314 144 344 164 360" stroke="#22C55E" stroke-width="19"/>
      <path d="M176 280 C176 318 188 346 208 360" stroke="#8B5CF6" stroke-width="19"/>
      <path d="M202 276 C222 314 232 338 248 356" stroke="#EC4899" stroke-width="19"/>
      <path d="M228 264 C256 294 270 320 282 344" stroke="#06B6D4" stroke-width="19"/>
      <path d="M250 246 C282 264 304 292 300 332" stroke="#EF4444" stroke-width="19"/>
      <path d="M118 236 C84 246 58 266 44 296" stroke="#84CC16" stroke-width="19"/>
    </g>
    <g fill="#FFFFFF" opacity=".85">
      <circle cx="66" cy="336" r="6"/><circle cx="112" cy="356" r="6"/><circle cx="164" cy="360" r="6"/>
      <circle cx="208" cy="360" r="6"/><circle cx="248" cy="356" r="6"/><circle cx="282" cy="344" r="6"/>
      <circle cx="300" cy="332" r="6"/><circle cx="44" cy="296" r="6"/>
    </g>

    <!-- body -->
    <path d="M82 188 C82 108 278 108 278 188 C278 258 238 288 180 288 C122 288 82 258 82 188 Z"
          fill="url(#bodyGrad)" stroke="#0F172A" stroke-width="4"/>

    <!-- antenna -->
    <path d="M180 114 V80" stroke="#0F172A" stroke-width="5" stroke-linecap="round"/>
    <circle cx="180" cy="72" r="16" fill="url(#glow)"/>
    <circle cx="180" cy="72" r="8" fill="#67E8F9" stroke="#0F172A" stroke-width="3"/>

    <!-- 19-platform badge -->
    <circle cx="258" cy="142" r="23" fill="url(#badgeGrad)" stroke="#0F172A" stroke-width="4"/>
    <text x="258" y="150" text-anchor="middle" font-family="system-ui, -apple-system, Segoe UI, sans-serif" font-size="20" font-weight="800" fill="#7C2D12">19</text>

    <!-- scarf -->
    <path d="M108 244 Q180 270 252 244 L248 264 Q180 288 112 264 Z" fill="url(#scarfGrad)" stroke="#0F172A" stroke-width="4"/>
    <path d="M124 260 Q108 288 96 306 L122 312 Q134 288 144 268 Z" fill="#7C3AED" stroke="#0F172A" stroke-width="4"/>

    <!-- face -->
    <g>
      $eyes
      <circle cx="112" cy="214" r="12" fill="#FB7185" opacity=".35"/>
      <circle cx="248" cy="214" r="12" fill="#FB7185" opacity=".35"/>
      $mouth
    </g>

    $tentacle
    $extra
  </g>
</svg>
''')

EYES_NORMAL = '''<ellipse cx="145" cy="182" rx="21" ry="25" fill="#FFFFFF" stroke="#0F172A" stroke-width="3"/>
      <ellipse cx="215" cy="182" rx="21" ry="25" fill="#FFFFFF" stroke="#0F172A" stroke-width="3"/>
      <circle cx="149" cy="187" r="10" fill="#0F172A"/><circle cx="219" cy="187" r="10" fill="#0F172A"/>
      <circle cx="153" cy="182" r="4" fill="#FFFFFF"/><circle cx="223" cy="182" r="4" fill="#FFFFFF"/>'''

EYES_HAPPY = '''<path d="M126 190 Q145 166 164 190" fill="none" stroke="#0F172A" stroke-width="7" stroke-linecap="round"/>
      <path d="M196 190 Q215 166 234 190" fill="none" stroke="#0F172A" stroke-width="7" stroke-linecap="round"/>'''

EYES_CLOSED_DOWN = '''<path d="M126 182 Q145 198 164 182" fill="none" stroke="#0F172A" stroke-width="7" stroke-linecap="round"/>
      <path d="M196 182 Q215 198 234 182" fill="none" stroke="#0F172A" stroke-width="7" stroke-linecap="round"/>'''

EYES_WIDE = '''<ellipse cx="145" cy="180" rx="25" ry="29" fill="#FFFFFF" stroke="#0F172A" stroke-width="3"/>
      <ellipse cx="215" cy="180" rx="25" ry="29" fill="#FFFFFF" stroke="#0F172A" stroke-width="3"/>
      <circle cx="145" cy="184" r="7" fill="#0F172A"/><circle cx="215" cy="184" r="7" fill="#0F172A"/>
      <circle cx="149" cy="178" r="3" fill="#FFFFFF"/><circle cx="219" cy="178" r="3" fill="#FFFFFF"/>'''

EYES_WINK = '''<ellipse cx="145" cy="182" rx="21" ry="25" fill="#FFFFFF" stroke="#0F172A" stroke-width="3"/>
      <circle cx="149" cy="187" r="10" fill="#0F172A"/><circle cx="153" cy="182" r="4" fill="#FFFFFF"/>
      <path d="M198 188 Q215 172 232 188" fill="none" stroke="#0F172A" stroke-width="7" stroke-linecap="round"/>'''

EYES_SPARKLE = '''<ellipse cx="145" cy="182" rx="24" ry="27" fill="#FFFFFF" stroke="#0F172A" stroke-width="3"/>
      <ellipse cx="215" cy="182" rx="24" ry="27" fill="#FFFFFF" stroke="#0F172A" stroke-width="3"/>
      <circle cx="149" cy="187" r="11" fill="#0F172A"/><circle cx="219" cy="187" r="11" fill="#0F172A"/>
      <circle cx="154" cy="181" r="5" fill="#FFFFFF"/><circle cx="224" cy="181" r="5" fill="#FFFFFF"/>
      <path d="M138 164 l5 -8 5 8 8 5 -8 5 -5 8 -5 -8 -8 -5Z" fill="#FDE68A" opacity=".9"/>'''

EYES_ANGRY = '''<ellipse cx="145" cy="184" rx="20" ry="22" fill="#FFFFFF" stroke="#0F172A" stroke-width="3"/>
      <ellipse cx="215" cy="184" rx="20" ry="22" fill="#FFFFFF" stroke="#0F172A" stroke-width="3"/>
      <circle cx="149" cy="188" r="9" fill="#0F172A"/><circle cx="219" cy="188" r="9" fill="#0F172A"/>
      <path d="M124 160 L166 172" fill="none" stroke="#0F172A" stroke-width="6" stroke-linecap="round"/>
      <path d="M236 160 L194 172" fill="none" stroke="#0F172A" stroke-width="6" stroke-linecap="round"/>'''

MOUTH_SMILE = '''<path d="M158 224 Q180 244 202 224" fill="none" stroke="#0F172A" stroke-width="6" stroke-linecap="round"/>'''
MOUTH_SMALL = '''<path d="M166 226 Q180 236 194 226" fill="none" stroke="#0F172A" stroke-width="6" stroke-linecap="round"/>'''
MOUTH_OPEN = '''<path d="M156 220 Q180 258 204 220 Z" fill="#0F172A"/>
      <path d="M168 236 Q180 226 192 236 Q180 248 168 236 Z" fill="#FB7185"/>'''
MOUTH_FROWN = '''<path d="M160 238 Q180 218 200 238" fill="none" stroke="#0F172A" stroke-width="6" stroke-linecap="round"/>'''
MOUTH_FLAT = '''<path d="M162 230 L198 230" fill="none" stroke="#0F172A" stroke-width="6" stroke-linecap="round"/>'''
MOUTH_GRIT = '''<rect x="158" y="222" width="44" height="15" rx="7" fill="#FFFFFF" stroke="#0F172A" stroke-width="4"/>
      <path d="M170 222 V237 M185 222 V237 M200 222 V237" stroke="#0F172A" stroke-width="2"/>'''
MOUTH_TONGUE = '''<path d="M158 224 Q180 244 202 224" fill="none" stroke="#0F172A" stroke-width="6" stroke-linecap="round"/>
      <path d="M172 234 Q180 254 188 234 Z" fill="#FB7185" stroke="#0F172A" stroke-width="3"/>'''

EXTRA_TEARS = '''<g class="tear">
      <path d="M128 204 q-7 18 0 26 q7 -8 0 -26Z" fill="#38BDF8"/>
      <path d="M232 204 q-7 18 0 26 q7 -8 0 -26Z" fill="#38BDF8"/>
    </g>'''
EXTRA_SWEAT = '''<path d="M250 158 q-9 18 0 26 q9 -8 0 -26Z" fill="#7DD3FC" stroke="#0F172A" stroke-width="3"/>'''
EXTRA_ANGER = '''<g stroke="#EF4444" stroke-width="6" stroke-linecap="round">
      <path d="M244 134 L262 152"/><path d="M262 134 L244 152"/>
      <path d="M270 120 q8 -10 16 0" fill="none"/><path d="M276 132 q8 -10 16 0" fill="none"/>
    </g>'''
EXTRA_HEART = '''<path d="M282 196 c0 -12 -16 -12 -16 0 c0 10 16 20 16 20 c0 0 16 -10 16 -20 c0 -12 -16 -12 -16 0Z" fill="#F472B6" stroke="#0F172A" stroke-width="3"/>'''
EXTRA_SPARKLE = '''<g class="spark" fill="#FDE68A">
      <path d="M78 160 l4 -10 4 10 10 4 -10 4 -4 10 -4 -10 -10 -4Z"/>
      <path d="M292 170 l3 -8 3 8 8 3 -8 3 -3 8 -3 -8 -8 -3Z"/>
      <path d="M96 250 l3 -7 3 7 7 3 -7 3 -3 7 -3 -7 -7 -3Z"/>
    </g>'''
EXTRA_CLOUD = '''<g fill="#94A3B8" opacity=".75">
      <path d="M110 108 q-18 0 -18 -14 q0 -14 16 -14 q4 -20 26 -20 q18 0 24 14 q20 -8 30 6 q18 0 18 14 q0 14 -18 14Z"/>
      <path d="M206 100 q-12 0 -12 -10 q0 -10 12 -10 q4 -14 18 -14 q12 0 16 10 q14 -6 20 4 q12 0 12 10 q0 10 -12 10Z" opacity=".7"/>
    </g>'''
EXTRA_MOTION = '''<g fill="none" stroke="#F59E0B" stroke-width="5" stroke-linecap="round" opacity=".8">
      <path d="M300 196 q18 -8 24 -24"/><path d="M306 212 q22 -4 32 -16"/>
    </g>'''
TENTACLE_KNOCK = '''<path d="M246 236 C282 214 300 184 306 154" fill="none" stroke="#F59E0B" stroke-width="19" stroke-linecap="round"/>
    <circle cx="306" cy="154" r="12" fill="#F59E0B" stroke="#0F172A" stroke-width="3"/>'''
TENTACLE_FACEPALM = '''<path d="M124 244 C96 214 108 182 146 176" fill="none" stroke="#F59E0B" stroke-width="19" stroke-linecap="round"/>
    <circle cx="146" cy="176" r="12" fill="#F59E0B" stroke="#0F172A" stroke-width="3"/>'''

EXPRESSIONS = {
    "smile": (EYES_NORMAL, MOUTH_SMILE, "", "", "smile"),
    "joy": (EYES_HAPPY, MOUTH_OPEN, EXTRA_SPARKLE, "", "joy"),
    "cry": (EYES_NORMAL, MOUTH_FROWN, EXTRA_TEARS, "", "cry"),
    "depressed": (EYES_CLOSED_DOWN, MOUTH_FLAT, EXTRA_CLOUD, "", "depressed"),
    "terrified": (EYES_WIDE, MOUTH_OPEN, EXTRA_SWEAT, "", "terrified"),
    "angry": (EYES_ANGRY, MOUTH_GRIT, EXTRA_ANGER, "", "angry"),
    "coquettish": (EYES_WINK, MOUTH_SMILE, EXTRA_HEART, "", "coquettish"),
    "cute": (EYES_SPARKLE, MOUTH_SMALL, EXTRA_SPARKLE, "", "cute"),
    "pleasing": (EYES_HAPPY, MOUTH_SMALL, EXTRA_SPARKLE, "", "pleasing"),
    "knocking": (EYES_NORMAL, MOUTH_SMILE, EXTRA_MOTION, TENTACLE_KNOCK, "knocking"),
    "facepalm": (EYES_CLOSED_DOWN, MOUTH_FLAT, "", TENTACLE_FACEPALM, "facepalm"),
    "tongue": (EYES_WINK, MOUTH_TONGUE, EXTRA_HEART, "", "tongue"),
}

DESC = ("一只青色卡通小章鱼，戴 PHP 紫围巾，八条彩色触手连接 SQL、Hadoop、湖仓、"
        "调度和 NoSQL，头顶挂着 19 的平台徽章。")


def render(name: str, eyes: str, mouth: str, extra: str, tentacle: str) -> str:
    title = "数据章鱼多多" if name == "smile" else f"数据章鱼多多 - {name}"
    return PET.substitute(
        title=title,
        desc=DESC,
        eyes=eyes,
        mouth=mouth,
        extra=extra,
        tentacle=tentacle,
    )


def main() -> None:
    (ROOT / "pet.svg").write_text(render("smile", *EXPRESSIONS["smile"][:4]), encoding="utf-8")
    for name, parts in EXPRESSIONS.items():
        (OUT / f"{name}.svg").write_text(render(name, *parts[:4]), encoding="utf-8")
    print(f"wrote pet.svg and {len(EXPRESSIONS)} expressions")


if __name__ == "__main__":
    main()
