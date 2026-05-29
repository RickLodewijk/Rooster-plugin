# Rooster Planner

Rooster Planner is een robuuste WordPress-plugin waarmee medewerkers eenvoudig hun beschikbaarheid en tijdslots kunnen doorgeven via een afgeschermde front-end pagina. Beheerders hebben de volledige controle via een uitgebreid dashboard om standaard roosters te definiëren, afwijkende dagen (zoals sluitingsdagen of evenementen) in te stellen en de totale bezetting in één oogopslag te bekijken.

## 🚀 Kenmerken

- **Automatische Setup:** Bij activatie maakt de plugin direct de benodigde pagina's aan (Inloggen, Formulier, Overzicht) inclusief de juiste shortcodes en metadata.
- **Dynamic Date Tracking:** Datums worden automatisch berekend op basis van het ISO-weeknummer en worden overzichtelijk getoond in zowel het invulformulier als de overzichtstabel.
- **Flexibele Beschikbaarheid:** Ondersteunt optioneel een "Misschien"-status per tijdslot (instelbaar door de beheerder).
- **Uitzonderingen & Sluitingsdagen:** Beheer eenvoudig afwijkende dagen of feestdagen in het admin-paneel.
- **Rollensysteem:** Introduceert de rol `Medewerker rooster` (`medewerker_rooster`) om de toegang tot de formulieren strak te beveiligen.

---

## 🛠️ Installatie & Setup

1. Download of kloon deze repository in de map `/wp-content/plugins/rooster-planner/`.
2. Zorg dat de bestandsstructuur er als volgt uitziet:
```text
   rooster-planner/
   ├── includes/
   │   ├── trait-admin.php
   │   ├── trait-data.php
   │   └── trait-frontend.php
   └── rooster-planner.php (Hoofdbestand)

```

3. Ga naar je WordPress Dashboard -> **Plugins** en activeer **Rooster Planner**.
4. **Klaar!** De plugin maakt direct de volgende pagina's voor je aan in WordPress:
* `Rooster Inloggen` (bevat de shortcode `[rooster_login]`)
* `Rooster Formulier` (toont het invulformulier)
* `Rooster Overzicht` (toont de complete tabel met de totale aanwezigheid)



> **Tip:** Heb je de plugin al geactiveerd voordat je de nieuwste code uploadde? Deactiveer de plugin en activeer hem opnieuw om de pagina's alsnog automatisch te laten aanmaken.

---

## 📖 Gebruik

### Voor Medewerkers

1. Ga naar de pagina **Rooster Formulier**.
2. Als je niet bent ingelogd, word je automatisch doorgestuurd naar de **Rooster Inloggen** pagina.
3. Selecteer de gewenste week in het dropdown-menu (standaard kun je tot 4 weken vooruitkijken).
4. Geef per dag je beschikbaarheid op via de radiobuttons/checkboxen en klik op **Opslaan**.

### Voor Beheerders (Admin Dashboard)

In het WordPress-menu verschijnt een nieuw hoofdmenu genaamd **Rooster**:

* **Instellingen:** Schakel hier de optie *"Sta 'Misschien' toe bij tijdslots"* in of uit.
* **Standaard roosterindeling:** Definieer hier per dag de vaste tijdslots (bijv. *Ochtend (09:00–13:00)* of *Middag (13:00–17:00)*).
* **Afwijkende dagen:** Voeg specifieke datums toe die afwijken van het standaardrooster, of vink *"Gesloten"* aan om een specifieke dag volledig te vergrendelen.
* **Inzendingen:** Bekijk of bewerk de ruwe database-inzendingen per medewerker.

---

## 🔑 Shortcodes

Mocht je de layouts op andere pagina's willen tonen, dan kun je gebruikmaken van de volgende shortcodes:

| Shortcode | Beschrijving |
| --- | --- |
| `[rooster_login]` | Toont het inlogformulier voor medewerkers (en stuurt ze na inloggen direct door naar het formulier). |
| `[rooster_overview]` | Toont de volledige matrix-tabel met alle planningen en het totaal aantal mensen per dag. |

---

## 💻 Technische Details & Structuur

De plugin maakt gebruik van PHP **Traits** om de code overzichtelijk, modulair en onderhoudbaar te houden:

* **`Rooster_Planner` (Hoofdbestand):** Regelt de plugin-activatie, hooks, shortcode-registraties en de globale constanten (zoals `WEEKS_AHEAD = 4`).
* **`Rooster_Planner_Admin`:** Behandelt de back-end pagina's, de pagina-specifieke Metaboxes (waarmee je handmatig pagina's kunt aanwijzen als formulier/overzicht) en de opslag van instellingen.
* **`Rooster_Planner_Frontend`:** Regelt de formulieren op de website, de validatie, beveiliging middels nonces, de CSS-styling van het overzicht en de dynamische datumweergave.
* **`Rooster_Planner_Data`:** De motor achter de schermen. Berekent datums op basis van tijdzones en weeknummers, filtert uitzonderingen en haalt de metadata op uit de database (`rooster_submission` post-type).

---

## 🛡️ Beveiliging

* Alle invoervelden worden gesaneerd met `sanitize_text_field()` en `sanitize_meta_field()`.
* Formulieren zijn beveiligd tegen CSRF-aanvallen met WordPress **Nonces** (`wp_verify_nonce`).
* Directe toegang tot data via URL's wordt geblokkeerd middels `ABSPATH` controles.
* Pagina's controleren strikt op de `rooster_access` capability.

## 📝 Licentie

Gemaakt door Rick Lodewijk