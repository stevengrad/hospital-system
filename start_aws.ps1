$REGION="eu-central-1"
$CLUSTER="hospital-"

$ALB_NAME="hospital-alb"
$SUBNET1="subnet-05b3c051ccc9b5c79"
$SUBNET2="subnet-000bcc12d7b048584"
$ALB_SG="sg-087857694001af684"

$CERT_ARN="arn:aws:acm:eu-central-1:137068224200:certificate/5238d04e-39c6-46d2-86bc-e2b2e4d05ecd"
$HOSTED_ZONE_ID="Z08358461X4HB2AFHPGHJ"

$WEB_TG="arn:aws:elasticloadbalancing:eu-central-1:137068224200:targetgroup/hospital-web-tg/06f08fca6148d504"
$CHATBOT_TG="arn:aws:elasticloadbalancing:eu-central-1:137068224200:targetgroup/hospital-chatbot-tg/b7e7a7ed998017df"
$FACE_TG="arn:aws:elasticloadbalancing:eu-central-1:137068224200:targetgroup/hospital-face-tg/59a620872442803c"
$OcrTargetGroupArn = "arn:aws:elasticloadbalancing:eu-central-1:137068224200:targetgroup/hospital-ocr-api-tg/fc68db18dee85093"

Write-Host "Starting RDS..."
aws rds start-db-instance --db-instance-identifier database-1 --region $REGION

Write-Host "Creating ALB..."
$ALB_ARN = aws elbv2 create-load-balancer `
  --name $ALB_NAME `
  --subnets $SUBNET1 $SUBNET2 `
  --security-groups $ALB_SG `
  --scheme internet-facing `
  --type application `
  --ip-address-type ipv4 `
  --query "LoadBalancers[0].LoadBalancerArn" `
  --output text `
  --region $REGION

aws elbv2 wait load-balancer-available --load-balancer-arns $ALB_ARN --region $REGION

$ALB_DNS = aws elbv2 describe-load-balancers --load-balancer-arns $ALB_ARN --query "LoadBalancers[0].DNSName" --output text --region $REGION
$ALB_ZONE = aws elbv2 describe-load-balancers --load-balancer-arns $ALB_ARN --query "LoadBalancers[0].CanonicalHostedZoneId" --output text --region $REGION

Write-Host "Creating HTTP listener..."
aws elbv2 create-listener `
  --load-balancer-arn $ALB_ARN `
  --protocol HTTP `
  --port 80 `
  --default-actions Type=redirect,RedirectConfig="{Protocol=HTTPS,Port=443,StatusCode=HTTP_301}" `
  --region $REGION

Write-Host "Creating HTTPS listener..."
$HTTPS_LISTENER_ARN = aws elbv2 create-listener `
  --load-balancer-arn $ALB_ARN `
  --protocol HTTPS `
  --port 443 `
  --certificates CertificateArn=$CERT_ARN `
  --ssl-policy ELBSecurityPolicy-TLS13-1-2-2021-06 `
  --default-actions Type=forward,TargetGroupArn=$WEB_TG `
  --query "Listeners[0].ListenerArn" `
  --output text `
  --region $REGION

Write-Host "Creating HTTPS rules..."
aws elbv2 create-rule `
  --listener-arn $HTTPS_LISTENER_ARN `
  --priority 1 `
  --conditions Field=path-pattern,Values="/chat/*" `
  --actions Type=forward,TargetGroupArn=$CHATBOT_TG `
  --region $REGION

aws elbv2 create-rule `
  --listener-arn $HTTPS_LISTENER_ARN `
  --priority 2 `
  --conditions Field=path-pattern,Values="/face/*" `
  --actions Type=forward,TargetGroupArn=$FACE_TG `
  --region $REGION

aws elbv2 create-rule `
  --listener-arn $HTTPS_LISTENER_ARN `
  --priority 30 `
  --conditions Field=path-pattern,Values="/ocr/*" `
  --actions Type=forward,TargetGroupArn=$OcrTargetGroupArn `
  --region $REGION
Write-Host "Updating Route 53..."
$changeBatch = @"
{
  "Changes": [
    {
      "Action": "UPSERT",
      "ResourceRecordSet": {
        "Name": "cairohospitals.click",
        "Type": "A",
        "AliasTarget": {
          "HostedZoneId": "$ALB_ZONE",
          "DNSName": "dualstack.$ALB_DNS",
          "EvaluateTargetHealth": true
        }
      }
    },
    {
      "Action": "UPSERT",
      "ResourceRecordSet": {
        "Name": "*.cairohospitals.click",
        "Type": "A",
        "AliasTarget": {
          "HostedZoneId": "$ALB_ZONE",
          "DNSName": "dualstack.$ALB_DNS",
          "EvaluateTargetHealth": true
        }
      }
    }
  ]
}
"@

$changeBatch | Out-File -Encoding ascii route53-change.json

aws route53 change-resource-record-sets `
  --hosted-zone-id $HOSTED_ZONE_ID `
  --change-batch file://route53-change.json

Write-Host "Starting ECS services..."
aws ecs update-service --cluster $CLUSTER --service hospital-web-service --desired-count 1 --region $REGION
aws ecs update-service --cluster $CLUSTER --service hospital-chatbot-service --desired-count 1 --region $REGION
aws ecs update-service --cluster $CLUSTER --service hospital-FceDetection-task-service-akupo8cp --desired-count 1 --region $REGION
aws ecs update-service --cluster $CLUSTER --service hospital-ocr-api-service-ts2g6l6c  --desired-count 1  --force-new-deployment --region $REGION
Write-Host "Start script finished."
Write-Host "ALB DNS: $ALB_DNS"