# Script pour installer les dependances de tous les services (Windows PowerShell)

Write-Host ">>> Installation des dependances pour tous les services..." -ForegroundColor Cyan

Write-Host ""
Write-Host "[article-service] composer install..." -ForegroundColor Yellow
docker-compose exec -T article-service composer install

Write-Host ""
Write-Host "[user-service] composer install..." -ForegroundColor Yellow
docker-compose exec -T user-service composer install

Write-Host ""
Write-Host "[frontend] npm install..." -ForegroundColor Yellow
docker-compose exec -T frontend npm install

Write-Host ""
Write-Host "[OK] Installation terminee !" -ForegroundColor Green

