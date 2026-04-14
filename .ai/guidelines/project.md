## Project Overview

Personal finance app — "MoneyCloud".
All UI text must be in **Slovenian**.

## Plan Mode

- Make the plan extremely concise. Sacrifice grammar for the sake of concision.
- At the end of each plan, give me a list of unresolved questions to answer, if any.

## Conventions

- no Axios
- Format numbers in Slovenian style: `1.234,56` (dot = thousands, comma = decimal)

## Elequent Models

- For model scopes use PHP Attribute #[Scope] (Laravel 12)
- Do not start elequent queries with ::query()

## TailwindCSS

- If size is needed, write as size-X not w-X h-X. For example, instead "h-4 w-4" write "size-4"