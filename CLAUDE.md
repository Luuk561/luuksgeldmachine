# Affiliate Control Dashboard

## Doel
Eén centrale beslishub voor 47 affiliate-websites.
Geen rapportage-tool, maar een **stuurinstrument**.

Beantwoordt: "Waar moet ik nu op focussen om meer te verdienen?"

---

## Ontwerpprincipes (KEIHARD)

- **UI rekent NOOIT** — alle berekeningen gebeuren vooraf via background jobs
- **Geen ruwe data in UI** — events/logs/API-responses zijn onzichtbaar voor frontend
- **Near-realtime is genoeg** — 5-15 min delay is acceptabel
- **Benchmarks = eigen data** — geen externe ranges, alleen % boven/onder jouw 30d-gemiddelde
- **Performance > features** — liever minder metrics die instant laden
- **Degrade gracefully** — freshness-indicator, geen crashes bij missing data

---

## Data-architectuur

### 3 Lagen

1. **Brondata (Raw)**
   Bol API-responses, Fathom-data, affiliate-clicks
   → Opslaan as-is, nooit wijzigen

2. **Verrijkte Data (Enriched)**
   Brondata + context (site_id, page_id, product_id)
   → Minimale verrijking, alleen wat nodig is voor drilldowns

3. **Metrics (Pre-Computed)**
   Geaggregeerde data per dag/7d/30d × dimensie
   → Dit leest de UI

---

## Drilldown-logica

```
Global Overview (alles bij elkaar)
    ↓
Per Site (welke site wijkt af?)
    ↓
Per Pagina (welke pagina binnen site?)
    ↓
Per Product (welk product op pagina?)
```

Elke stap beantwoordt één vraag → leidt tot beslissing.

---

## Databronnen

| Bron | Data | Frequentie |
|------|------|------------|
| **Bol.com API** | Orders, commissies, EAN | 1x per dag |
| **Fathom** | Bezoekers, pageviews | Elk uur |
| **Affiliate clicks** | Outbound clicks | Elk kwartier |
| **Site-databases** | Sites, pagina's, producten | On-demand |

---

## Metrics (v1, hard limit)

- Commissie (€)
- Orders (#)
- Clicks (#)
- Bezoekers (#)
- Pageviews (#)
- RPV (€ commissie / bezoekers)
- Conversieratio (orders / clicks)

**Niet in v1:**
Engagement, UTM-data, geo, device — komt later.

---

## Tijdvensters & Waarheid

- **Vandaag:** 00:00 tot nu
- **7d:** rolling (laatste 7 dagen)
- **30d:** rolling (voor benchmarks)

**Attributie:**
- Click geteld op clickdatum
- Order geteld op orderdatum
- Latency click → order = 0-30 dagen (acceptabel)

---

## Reporting vs Decision Mode

**Decision Mode (default):**
KPI's + drilldowns, focus op outliers, instant load

**Reporting Mode (apart):**
Exports, tijdreeksen, data-kwaliteit, audit-trail

---

## Tech Stack

- Laravel (backend + queue)
- Blade + Alpine.js + Tailwind (frontend)
- Redis + Horizon (queue monitoring)
- MySQL (metrics storage)

---

## Stijl

Apple-esque + glassmorphism:
- Ruime marges, grote cijfers
- Frosted glass-effecten
- Subtiele depth, smooth transitions
- Focus op essentie, geen opsmuk
