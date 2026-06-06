# ==============================
# Start RDS Only
# Hospital Project
# Region: eu-central-1
# ==============================

$Region = "eu-central-1"
$DBIdentifier = "database-1"

Write-Host "Starting RDS instance: $DBIdentifier ..." -ForegroundColor Cyan

# Check current RDS status
$Status = aws rds describe-db-instances `
    --db-instance-identifier $DBIdentifier `
    --region $Region `
    --query "DBInstances[0].DBInstanceStatus" `
    --output text

Write-Host "Current RDS status: $Status" -ForegroundColor Yellow

if ($Status -eq "available") {
    Write-Host "RDS is already running." -ForegroundColor Green
    exit
}

if ($Status -eq "starting") {
    Write-Host "RDS is already starting. Waiting until available..." -ForegroundColor Yellow
}
elseif ($Status -eq "stopped") {
    aws rds start-db-instance `
        --db-instance-identifier $DBIdentifier `
        --region $Region

    Write-Host "Start command sent. Waiting until RDS becomes available..." -ForegroundColor Cyan
}
else {
    Write-Host "RDS cannot be started now because current status is: $Status" -ForegroundColor Red
    exit
}

# Wait until RDS is available
aws rds wait db-instance-available `
    --db-instance-identifier $DBIdentifier `
    --region $Region

Write-Host "RDS is now running and available." -ForegroundColor Green