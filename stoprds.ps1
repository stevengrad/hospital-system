# ==============================
# Stop RDS Only
# Hospital Project
# Region: eu-central-1
# ==============================

$Region = "eu-central-1"
$DBIdentifier = "database-1"

Write-Host "Stopping RDS instance: $DBIdentifier ..." -ForegroundColor Cyan

# Check current RDS status
$Status = aws rds describe-db-instances `
    --db-instance-identifier $DBIdentifier `
    --region $Region `
    --query "DBInstances[0].DBInstanceStatus" `
    --output text

Write-Host "Current RDS status: $Status" -ForegroundColor Yellow

if ($Status -eq "stopped") {
    Write-Host "RDS is already stopped." -ForegroundColor Green
    exit
}

if ($Status -eq "stopping") {
    Write-Host "RDS is already stopping. Waiting until stopped..." -ForegroundColor Yellow
}
elseif ($Status -eq "available") {
    aws rds stop-db-instance `
        --db-instance-identifier $DBIdentifier `
        --region $Region

    Write-Host "Stop command sent. Waiting until RDS becomes stopped..." -ForegroundColor Cyan
}
else {
    Write-Host "RDS cannot be stopped now because current status is: $Status" -ForegroundColor Red
    exit
}

# Wait until RDS is stopped
aws rds wait db-instance-stopped `
    --db-instance-identifier $DBIdentifier `
    --region $Region

Write-Host "RDS is now stopped." -ForegroundColor Green