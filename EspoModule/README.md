# PdfFilterHelper – EspoCRM Custom Module

Fügt einen neuen Handlebars-Helper `{{#eachFiltered}}` für PDF-Templates hinzu, der Relations **serverseitig** filtert, sortiert und limitiert.

---

## Warum?

EspoCRM's eingebautes `{{#each relation}}` lädt alle verknüpften Datensätze (bis zu `htmlizerLinkLimit`, default 100) und überlässt die Filterung dem Template.  
`{{#eachFiltered}}` schiebt Filter + Sortierung + Limit in den ORM-Query **vor** dem Laden – effizienter, sicherer, flexibler.

---

## Installation

```
custom/
└── Espo/
    └── Modules/
        └── PdfFilterHelper/
            ├── Filter/
            │   ├── Tokenizer.php
            │   ├── TokenType.php
            │   ├── Token.php
            │   ├── TokenStream.php
            │   ├── Parser.php
            │   ├── FilterParser.php          ← Public Façade
            │   ├── WhereClauseCompiler.php
            │   └── Exception/
            │       └── Exceptions.php
            ├── TemplateHelper/
            │   └── EachFiltered.php          ← Espo Helper Entry Point
            ├── Resources/
            │   └── metadata/
            │       └── app/
            │           └── templateHelpers.json
            └── manifest.json
```

1. Dateien hochladen
2. Admin → Rebuild (Cache leeren)

---

## Verwendung im PDF-Template

```handlebars
{{#eachFiltered
    link='kundentermine'
    where='dateStart >= today && status in ["Planned","Confirmed"]'
    orderBy='dateStart'
    order='asc'
    limit=20
}}
<tr>
  <td>{{name}}</td>
  <td>{{dateStart}}</td>
  <td>{{status}}</td>
</tr>
{{/eachFiltered}}
```

### Parameter

| Parameter | Typ    | Pflicht | Default | Beschreibung |
|-----------|--------|---------|---------|--------------|
| `link`    | string | ✅      | –       | Relation-Name auf der aktuellen Entity |
| `where`   | string | ❌      | –       | Filter-Ausdruck (siehe Syntax unten) |
| `orderBy` | string | ❌      | –       | Feldname zum Sortieren |
| `order`   | string | ❌      | `asc`   | `asc` oder `desc` |
| `limit`   | int    | ❌      | 20      | Max. Datensätze (Obergrenze: 200) |

### Template-Variablen

Innerhalb des Blocks stehen alle Felder der verknüpften Entity zur Verfügung, plus:

| Variable  | Bedeutung |
|-----------|-----------|
| `@index`  | 0-basierter Index |
| `@first`  | `true` beim ersten Element |
| `@last`   | `true` beim letzten Element |

---

## Filter-Syntax

### Vergleichsoperatoren

```
field == value
field != value
field >  value
field >= value
field <  value
field <= value
```

### IN-Operator

```
status in ["Planned", "Confirmed", "Held"]
priority in [1, 2, 3]
```

### Logische Operatoren

```
expr1 && expr2    // AND
expr1 || expr2    // OR
(expr1 || expr2) && expr3   // Klammerung
```

### Literale

| Typ     | Beispiele |
|---------|-----------|
| String  | `"Planned"` oder `'Planned'` |
| Zahl    | `42`, `9.99`, `-5` |
| Boolean | `true`, `false` |
| Null    | `null` |

### Spezialwerte

| Wert    | Bedeutung |
|---------|-----------|
| `today` | Heute als `Y-m-d` (Mitternacht) |
| `now`   | Aktueller Zeitstempel als `Y-m-d H:i:s` |

### Beispiele

```
// Nur zukünftige, geplante Termine
dateStart >= today && status in ["Planned","Confirmed"]

// Rechnungen über 500€ die nicht storniert sind
amount > 500 && status != "Cancelled"

// Komplexe Klammerung
(status == "Planned" || status == "Confirmed") && assignedUserId != null

// Negative Zahl
balance < -100

// Null-Check
assignedUser == null
```

---

## Architektur

```
Template-String
      │
      ▼
 [Tokenizer]          String → Token[]
      │                (FIELD, OP, STRING, NUMBER, BOOL, NULL, SPECIAL, IN, AND, OR, ...)
      ▼
 [Parser]             Tokens → AST
      │                (ComparisonNode, InNode, LogicalNode, ...)
      ▼
 [WhereClauseCompiler]  AST → Espo WhereClause-Array
      │                  { 'field>=' => '2024-01-01', 'AND' => [...] }
      ▼
 [EachFiltered Helper]
      │   $entity->getRelation($link)->findBuilder()
      │       ->where(WhereClause::fromRaw($whereArray))
      │       ->order($orderBy, $direction)
      │       ->limit(0, $limit)
      │       ->find()
      ▼
  HTML-Output (gerenderte Block-Iteration)
```

### Sicherheitsdesign

- ❌ Kein SQL im Template
- ❌ Kein freier PHP-Code
- ✅ Feldnamen werden per Regex validiert (`/^[a-zA-Z_][a-zA-Z0-9_.]*$/`)
- ✅ Alle Werte werden als typisierte PHP-Primitives übergeben, nie als SQL-Strings
- ✅ Espo's ORM übernimmt die Parameterisierung
- ✅ `limit` ist auf max. 200 gedeckelt

---

## Tests

```bash
./vendor/bin/phpunit tests/Filter/FilterParserTest.php
```

Getestete Fälle: Vergleichsoperatoren, IN, `&&`/`||`, Klammerung, Spezialwerte, Fehlerbehandlung (Injection-Versuche, Syntaxfehler).

---

## Erweiterungsmöglichkeiten (Version 2+)

- `NOT` Operator
- `IS NULL` / `IS NOT NULL` Shortcuts
- `BETWEEN` Operator
- `dateStart BETWEEN today AND +30days`
- Whitelist erlaubter Felder pro Entity-Typ
- Caching kompilierter Expressions
