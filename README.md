<p align="center">
   <img src="https://raw.githubusercontent.com/BostjanOb/MoneyCloud/refs/heads/main/public/logo.svg" width="400" alt="MoneyCloud logo">

   <img src="https://raw.githubusercontent.com/BostjanOb/MoneyCloud/refs/heads/main/screenshot.png" alt="MoneyCloud screenshot">
</p>

# MoneyCloud

MoneyCloud is a personal finance app for managing everyday money in one place. It started with the **Plače** module for paycheck tracking and tax calculations, and now also includes savings, investments, crypto tracking, people management, and simple financial statistics.

The application UI is in **Slovenian** and is built for a practical, clean overview of personal finances.

## What you can do

- track paychecks, bonuses, and tax settings
- manage savings accounts and balance changes
- track investments, providers, and purchases
- follow crypto balances and DCA purchases
- view monthly and yearly statistics

## Setup

1. Clone the repository.
2. Install backend dependencies:

```bash
composer install
```

3. Install frontend dependencies:

```bash
npm install
```

4. Create your environment file:

```bash
cp .env.example .env
```

5. Configure your database in `.env`.
   You can use SQLite for a quick local setup or MySQL if that matches your environment.

6. Generate the app key and run migrations:

```bash
php artisan key:generate
php artisan migrate --seed
```

7. Start the development environment:

```bash
composer run dev
```

If you are using Laravel Herd, the project is typically available at [http://moneycloud.test](http://moneycloud.test).

## Notes

- keep Node.js, PHP, and Composer installed locally
- if frontend changes are not visible, run `npm run dev` or `npm run build`
- this project uses Laravel, Inertia, Vue, and Tailwind CSS
