# Globotany (NASA Space Apps 2025)

Globotany compares vegetation blooming between years using NASA GIBS true-color imagery and an OpenAI-generated report. Users pick an area and dates, capture two VIIRS NOAA-20 images, and receive an HTML report ready to print.

## Repository layout
- `index.html` - Splash and onboarding (pt-BR) that links to the map experience.
- `globotany.php` - Leaflet map UI, NASA GIBS WMS layers, swipe/capture workflow, analysis trigger, and print view.
- `analyse.php` - PHP JSON API: validates payload, downloads WMS images, composites them with GD, calls OpenAI Responses (gpt-5), and returns the HTML report. Reads `OPENAI_API_KEY` from `.env`.

## How it works (flow)
1) User opens `/` and goes through onboarding, then enters `globotany.php`.
2) Leaflet loads VIIRS NOAA-20 Corrected Reflectance True Color via WMS; slider reveals A vs B dates; date picker fetches available range from WMTS DescribeDomains.
3) Capture builds two GetMap URLs for the current viewport and loads them into the gallery view.
4) Analyze sends JSON to `/analyse.php` (default JS target: `https://globotany.cwiz.com.br/analyse.php`; change to a relative path for local dev).
5) Backend downloads both images, stitches them side by side (GD), and calls OpenAI Responses `gpt-5` with a remote-sensing prompt.
6) API returns structured HTML5; frontend shows it and can print a PDF-ready page with embedded data URLs.

## Requirements
- PHP 8.4+ with `curl` and `gd` extensions enabled.
- Internet access to `gibs.earthdata.nasa.gov` (WMS/WMTS) and `api.openai.com`.
- Write access for logs (`/var/log/globotany.log`) and system temp directory.
- Browser with JavaScript; Leaflet and assets are loaded from CDN.

## Setup (local)
1) Clone the repo.
2) Create `.env` in the project root:
   ```
   OPENAI_API_KEY=sk-...
   ```
3) In `analyse.php`, set `$ENV = 'DEV';` while developing (relaxes CORS/HTTPS). In production keep `PROD` and adjust `$ALLOW_ORIGINS`.
4) Start a PHP dev server: `php -S localhost:8000`.
5) Open `http://localhost:8000/index.html?v=2025-09-28-2` in your browser.

## API
- Endpoint: `POST /analyse.php`
- Headers: `Content-Type: application/json`
- Body example:
  ```json
  {
    "dateA": "2020-04-06",
    "dateB": "2023-04-06",
    "urlA": "https://gibs...GetMap&time=2020-04-06",
    "urlB": "https://gibs...GetMap&time=2023-04-06",
    "center": {"lat": 45.938, "lng": -117.050}
  }
  ```
- Success (200):
  ```json
  {
    "ok": true,
    "analysis_text": "<article>...</article>",
    "openai_raw": {"output": [...]},
    "meta": {
      "received_at": "2025-02-02T00:00:00Z",
      "center_deg": {"lat":45.938,"lng":-117.050},
      "days_between": 1095,
      "composite_path": "/tmp/globo_img_xxx.jpg",
      "warnA": "urlA_time_mismatch" // optional
    },
    "received": { ...echo of request... }
  }
  ```
- Validation errors: `422` (`missing_field`, `bad_dateA`, `bad_urlB`, `center_out_of_range`).
- CORS/HTTPS: `403` (`origin_not_allowed`, `https_required`).
- Missing key: `500 openai_api_key_missing`.
- Other failures: `500 analysis_failed` with a message.

## Frontend notes
- Default dates: A = 2020-04-06, B = 2023-04-06. Edit at the top of `globotany.php`.
- Default map extent is set near Idaho (USA); adjust `$west/$south/$east/$north` as needed.
- Date picker limits come from WMTS DescribeDomains; fallback range is 2017-12-13 to today.
- Print view embeds data URLs for both images and uses the returned HTML report; browsers can print to PDF.

## Configuration knobs
- `analyse.php`: `$ENV` (`DEV` skips HTTPS + broad CORS; `PROD` enforces both), `$ALLOW_ORIGINS`, `$LOG_FILE`.
- Logging: appends to `/var/log/globotany.log`; change the path if the server lacks permission.
- Prompt: in `analyse.php` inside `openai_analyze` pipeline; edit to tweak tone or length.
- Frontend endpoint: update the fetch URL in `globotany.php` if you deploy the API under a different host/path.

## Deployment tips
- Serve via HTTPS in production (required by `analyse.php`).
- Keep frontend and API on the same origin or list the frontend in `$ALLOW_ORIGINS`.
- Do not commit `.env`; rotate `OPENAI_API_KEY` regularly.
- Ensure temp storage has room for generated composites; each request writes one JPEG then returns the path in `meta`.

## Credits
- NASA GIBS (VIIRS NOAA-20 Corrected Reflectance True Color).
- Leaflet for mapping.
- OpenAI Responses API (gpt-5) for the analytical report.
- Built for NASA Space Apps Challenge 2025 by the Globotany team.

## License
MIT
