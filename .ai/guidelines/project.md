# AGENT.md

## Project Overview

Personal finance app — "Moje Finance". First module: **Plače** (Paychecks).
All UI text must be in **Slovenian**.

## Key Architecture Decisions

1. **Employee is a PHP Enum** — no table
2. **TaxCalculationService** — a pure service class that takes a PaycheckYear and returns the full calculation breakdown. Controllers call this service; Vue receives the result as props.
3. **Tax settings are time-ranged** — `year_from` / `year_to`. When calculating, find the setting where `year_from <= year AND (year_to >= year OR year_to IS NULL)`.
4. **Child relief is prorated** — `olajsava_otrok1 / 12 * child1_months`. Months are set per paycheck_year.

## Conventions

- no Axios
- Decimal columns: `decimal(10,2)` for money, `decimal(12,2)` for tax brackets
- Format numbers in Slovenian style: `1.234,56` (dot = thousands, comma = decimal)
- Use `Intl.NumberFormat('sl-SI', { minimumFractionDigits: 2 })` in Vue for formatting

## Tax Calculation Algorithm

```
SUM_GROSS        = sum of all paycheck.gross (bonuses NOT included)
SUM_CONTRIBUTIONS = sum of all paycheck.contributions
SUM_TAXES        = sum of all paycheck.taxes (akontacija — for comparison only)

OSNOVA           = SUM_GROSS - SUM_CONTRIBUTIONS
OLAJSAVE         = general_relief
                 + (child_relief1 / 12 * child1_months)
                 + (child_relief2 / 12 * child2_months)
                 + (child_relief3 / 12 * child3_months)
DAVCNA_OSNOVA    = max(0, OSNOVA - OLAJSAVE)

DOHODNINA        = apply tax brackets to DAVCNA_OSNOVA:
  Find bracket where bracket_from < DAVCNA_OSNOVA <= bracket_to
  DOHODNINA = base_tax + (DAVCNA_OSNOVA - bracket_from) * rate / 100

RAZLIKA          = DOHODNINA - SUM_TAXES
  Positive → doplačilo (owe more)
  Negative → vračilo (refund)
```
## Common Gotchas

- Bonus amounts are NOT part of gross sum for tax calculation
- `child*_months` can be 0 — meaning that child's relief is not applied that year
- When no tax_settings exist for a year, show a warning in UI, don't crash