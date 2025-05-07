@echo off
echo Setting up Legal Books Library...

echo Installing Intervention/Image package for generating book covers...
composer require intervention/image

echo Creating storage link...
php artisan storage:link

echo Running migrations...
php artisan migrate

echo Seeding legal books...
php artisan db:seed --class=LegalBookSeeder

echo Generating book cover images...
php artisan legal-books:generate-covers

echo Done! Legal Books Library is ready to use.
echo API endpoints:
echo - GET /api/legal-books
echo - GET /api/legal-books/categories
echo - GET /api/legal-books/category/{category}
echo - GET /api/legal-books/{id}
echo - GET /api/legal-books/{id}/download 