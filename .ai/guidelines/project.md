# AGENT.md

## Project Overview

Personal finance app — "Moje Finance". First module: **Plače** (Paychecks).
All UI text must be in **Slovenian**.

## Key Architecture Decisions

1. **Employee is a PHP Enum** — no table
2. **TaxCalculationService** — a pure service class that takes a PaycheckYear and returns the full calculation breakdown. Controllers call this service; Vue receives the result as props.

## Conventions

- no Axios
- Decimal columns: `decimal(10,2)` for money, `decimal(12,2)` for tax brackets
- Format numbers in Slovenian style: `1.234,56` (dot = thousands, comma = decimal)