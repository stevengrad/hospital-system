"""
YOLOv8 FACE DETECTION TRAINING SCRIPT
Simple script to train a custom YOLOv8 model for face detection
"""

from ultralytics import YOLO
import torch
import os

# ==================== CONFIGURATION ====================

# Dataset configuration
DATA_YAML = "data.yaml"  # Path to your data.yaml file

# Model configuration
MODEL_SIZE = "yolov8n.pt"  # Options: yolov8n, yolov8s, yolov8m, yolov8l, yolov8x
                            # n = nano (fastest, smallest)
                            # s = small
                            # m = medium
                            # l = large
                            # x = extra large (most accurate)

# Training hyperparameters
EPOCHS = 100              # Number of training epochs (50-300 recommended)
BATCH_SIZE = 16           # Batch size (8, 16, 32 depending on GPU memory)
IMAGE_SIZE = 640          # Input image size (640 is standard)
WORKERS = 8               # Number of data loading workers
DEVICE = 0                # GPU device (0, 1, 2...) or 'cpu'

# Project organization
PROJECT_NAME = "face_detection"
EXPERIMENT_NAME = "yolov8n_face_v1"

# Advanced settings
PATIENCE = 50             # Early stopping patience
LEARNING_RATE = 0.01      # Initial learning rate (auto if None)
OPTIMIZER = 'auto'        # Options: SGD, Adam, AdamW, auto
SAVE_PERIOD = 10          # Save checkpoint every N epochs
RESUME = False            # Resume from last checkpoint

# ==================== FUNCTIONS ====================

def check_environment():
    """Check if environment is properly configured"""
    print("\n" + "="*60)
    print("   ENVIRONMENT CHECK")
    print("="*60)
    
    # Check CUDA availability
    if torch.cuda.is_available():
        print(f"✅ GPU Available: {torch.cuda.get_device_name(0)}")
        print(f"   GPU Memory: {torch.cuda.get_device_properties(0).total_memory / 1e9:.2f} GB")
        print(f"   CUDA Version: {torch.version.cuda}")
    else:
        print("⚠️  No GPU detected - training will be slow on CPU")
    
    # Check dataset
    if os.path.exists(DATA_YAML):
        print(f"✅ Dataset config found: {DATA_YAML}")
    else:
        print(f"❌ ERROR: {DATA_YAML} not found!")
        return False
    
    print("="*60 + "\n")
    return True


def train_model():
    """Train YOLOv8 model on face detection dataset"""
    
    # Check environment
    if not check_environment():
        print("Please fix the errors above before training.")
        return
    
    print("\n" + "="*60)
    print("   STARTING YOLOV8 FACE DETECTION TRAINING")
    print("="*60)
    print(f"Model: {MODEL_SIZE}")
    print(f"Dataset: {DATA_YAML}")
    print(f"Epochs: {EPOCHS}")
    print(f"Batch Size: {BATCH_SIZE}")
    print(f"Image Size: {IMAGE_SIZE}")
    print(f"Device: {DEVICE}")
    print("="*60 + "\n")
    
    # Load model
    print("Loading model...")
    model = YOLO(MODEL_SIZE)
    
    # Start training
    print("\n🚀 Training started...\n")
    
    results = model.train(
        data=DATA_YAML,
        epochs=EPOCHS,
        batch=BATCH_SIZE,
        imgsz=IMAGE_SIZE,
        device=DEVICE,
        workers=WORKERS,
        project=PROJECT_NAME,
        name=EXPERIMENT_NAME,
        patience=PATIENCE,
        lr0=LEARNING_RATE,
        optimizer=OPTIMIZER,
        save_period=SAVE_PERIOD,
        resume=RESUME,
        
        # Data augmentation (adjust as needed)
        hsv_h=0.015,        # HSV-Hue augmentation
        hsv_s=0.7,          # HSV-Saturation augmentation
        hsv_v=0.4,          # HSV-Value augmentation
        degrees=0.0,        # Rotation (+/- deg)
        translate=0.1,      # Translation (+/- fraction)
        scale=0.5,          # Scaling (+/- gain)
        shear=0.0,          # Shear (+/- deg)
        flipud=0.0,         # Flip up-down probability
        fliplr=0.5,         # Flip left-right probability
        mosaic=1.0,         # Mosaic augmentation probability
        mixup=0.0,          # Mixup augmentation probability
        
        # Verbosity
        verbose=True,
        plots=True,         # Save training plots
        save=True,          # Save checkpoints
    )
    
    print("\n" + "="*60)
    print("   TRAINING COMPLETED!")
    print("="*60)
    print(f"\n📊 Results saved to: {PROJECT_NAME}/{EXPERIMENT_NAME}/")
    print(f"🏆 Best model: {PROJECT_NAME}/{EXPERIMENT_NAME}/weights/best.pt")
    print(f"📈 Last model: {PROJECT_NAME}/{EXPERIMENT_NAME}/weights/last.pt")
    print("\n" + "="*60 + "\n")
    
    return results


def validate_model(weights_path=None):
    """Validate trained model"""
    
    if weights_path is None:
        weights_path = f"{PROJECT_NAME}/{EXPERIMENT_NAME}/weights/best.pt"
    
    print("\n" + "="*60)
    print("   VALIDATING MODEL")
    print("="*60 + "\n")
    
    # Load model
    model = YOLO(weights_path)
    
    # Validate
    results = model.val(data=DATA_YAML)
    
    print("\n" + "="*60)
    print("   VALIDATION RESULTS")
    print("="*60)
    print(f"mAP50: {results.box.map50:.4f}")
    print(f"mAP50-95: {results.box.map:.4f}")
    print(f"Precision: {results.box.mp:.4f}")
    print(f"Recall: {results.box.mr:.4f}")
    print("="*60 + "\n")


def test_model(image_path, weights_path=None):
    """Test model on a single image"""
    
    if weights_path is None:
        weights_path = f"{PROJECT_NAME}/{EXPERIMENT_NAME}/weights/best.pt"
    
    print(f"\n🧪 Testing model on: {image_path}\n")
    
    # Load model
    model = YOLO(weights_path)
    
    # Predict
    results = model.predict(
        source=image_path,
        conf=0.25,
        save=True,
        project=PROJECT_NAME,
        name=f"{EXPERIMENT_NAME}_predictions"
    )
    
    print(f"\n✅ Predictions saved to: {PROJECT_NAME}/{EXPERIMENT_NAME}_predictions/")


def export_model(weights_path=None, format='onnx'):
    """Export model to different formats"""
    
    if weights_path is None:
        weights_path = f"{PROJECT_NAME}/{EXPERIMENT_NAME}/weights/best.pt"
    
    print(f"\n📦 Exporting model to {format.upper()}...\n")
    
    # Load model
    model = YOLO(weights_path)
    
    # Export
    model.export(format=format)
    
    print(f"\n✅ Model exported successfully!")


# ==================== MAIN ====================

if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="YOLOv8 Face Detection Training")
    parser.add_argument('--mode', type=str, default='train', 
                       choices=['train', 'validate', 'test', 'export'],
                       help='Mode: train, validate, test, or export')
    parser.add_argument('--weights', type=str, default=None,
                       help='Path to weights file')
    parser.add_argument('--image', type=str, default=None,
                       help='Path to test image')
    parser.add_argument('--format', type=str, default='onnx',
                       choices=['onnx', 'torchscript', 'coreml', 'tflite'],
                       help='Export format')
    
    args = parser.parse_args()
    
    if args.mode == 'train':
        train_model()
        
    elif args.mode == 'validate':
        validate_model(args.weights)
        
    elif args.mode == 'test':
        if args.image is None:
            print("❌ Please provide --image path for testing")
        else:
            test_model(args.image, args.weights)
            
    elif args.mode == 'export':
        export_model(args.weights, args.format)
