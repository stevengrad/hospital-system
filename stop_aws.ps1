$REGION="eu-central-1"
$CLUSTER="hospital-"
$ALB_NAME="hospital-alb"

Write-Host "Stopping ECS Services..."

aws ecs update-service --cluster $CLUSTER --service hospital-web-service --desired-count 0 --region $REGION
aws ecs update-service --cluster $CLUSTER --service hospital-chatbot-service --desired-count 0 --region $REGION
aws ecs update-service --cluster $CLUSTER --service hospital-FceDetection-task-service-akupo8cp --desired-count 0 --region $REGION
aws ecs update-service --cluster $CLUSTER --service hospital-ocr-api-service-ts2g6l6c --desired-count 0  --region $REGION

Write-Host "Stopping RDS..."

aws rds stop-db-instance --db-instance-identifier database-1 --region $REGION

Write-Host "Deleting ALB..."

$ALB_ARN = aws elbv2 describe-load-balancers --names $ALB_NAME --query "LoadBalancers[0].LoadBalancerArn" --output text --region $REGION

if ($ALB_ARN -ne "None") {
    aws elbv2 delete-load-balancer --load-balancer-arn $ALB_ARN --region $REGION
    Write-Host "ALB deleted."
}

Write-Host "Stop script finished."