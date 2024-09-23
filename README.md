# PHP and MySQL versions
- PHP: [7.4.32](https://www.php.net/releases/)
- MySQL: [8.0.39](https://dev.mysql.com/downloads/installer/)
- Node: [16.20.2](https://nodejs.org/en/blog/release/v16.20.2)
- Composer: [2.7.9](https://getcomposer.org/download/)

# Commands for set up

## Installing node and laravel packages
```
npm install
composer install
```

## Migrate and seed the database
```
php artisan migrate
php artisan db:seed
```

## Running locally
```
php artisan serve
```

#### Please rename `.env.sample` to `.env` before running locally
#### Uncomment ;extension=fileinfo and other relevant extensions from php.ini
