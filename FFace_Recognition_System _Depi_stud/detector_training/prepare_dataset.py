"""
DATASET PREPARATION HELPER
Helps organize and prepare face detection dataset for YOLOv8 training
"""

import os
import shutil
import random
from pathlib import Path

# ==================== CONFIGURATION ====================

SOURCE_IMAGES = "raw_images"      # Folder with your images
SOURCE_LABELS = "raw_labels"      # Folder with your labels (YOLO format)
OUTPUT_DIR = "dataset"            # Output organized dataset

TRAIN_RATIO = 0.8                 # 80% for training
VAL_RATIO = 0.2                   # 20% for validation
# TEST_RATIO = 0.1                # Optional: 10% for testing

RANDOM_SEED = 42                  # For reproducible splits

# ==================== FUNCTIONS ====================

def create_directory_structure(output_dir):
    """Create the required directory structure"""
    
    print("\n📁 Creating directory structure...")
    
    dirs = [
        f"{output_dir}/images/train",
        f"{output_dir}/images/val",
        f"{output_dir}/images/test",
        f"{output_dir}/labels/train",
        f"{output_dir}/labels/val",
        f"{output_dir}/labels/test",
    ]
    
    for dir_path in dirs:
        os.makedirs(dir_path, exist_ok=True)
        print(f"   ✅ Created: {dir_path}")


def get_image_label_pairs(images_dir, labels_dir):
    """Find matching image-label pairs"""
    
    print(f"\n🔍 Scanning for image-label pairs...")
    
    # Supported image extensions
    image_exts = ['.jpg', '.jpeg', '.png', '.bmp', '.webp']
    
    # Get all images
    image_files = []
    for ext in image_exts:
        image_files.extend(list(Path(images_dir).glob(f'*{ext}')))
        image_files.extend(list(Path(images_dir).glob(f'*{ext.upper()}')))
    
    # Find matching labels
    pairs = []
    for img_path in image_files:
        label_path = Path(labels_dir) / f"{img_path.stem}.txt"
        
        if label_path.exists():
            pairs.append((img_path, label_path))
        else:
            print(f"   ⚠️  No label for: {img_path.name}")
    
    print(f"   ✅ Found {len(pairs)} valid image-label pairs")
    return pairs


def verify_label_format(label_path):
    """Verify label is in correct YOLO format"""
    
    try:
        with open(label_path, 'r') as f:
            lines = f.readlines()
        
        for line in lines:
            parts = line.strip().split()
            if len(parts) != 5:
                return False, "Wrong number of values"
            
            class_id = int(parts[0])
            x, y, w, h = map(float, parts[1:])
            
            # Check if normalized (0-1)
            if not (0 <= x <= 1 and 0 <= y <= 1 and 0 <= w <= 1 and 0 <= h <= 1):
                return False, "Values not normalized (0-1)"
        
        return True, "OK"
    
    except Exception as e:
        return False, str(e)


def split_dataset(pairs, train_ratio, val_ratio, random_seed=42):
    """Split dataset into train/val/test sets"""
    
    print(f"\n✂️  Splitting dataset...")
    print(f"   Train: {train_ratio*100:.0f}%")
    print(f"   Val: {val_ratio*100:.0f}%")
    
    # Shuffle pairs
    random.seed(random_seed)
    random.shuffle(pairs)
    
    total = len(pairs)
    train_size = int(total * train_ratio)
    val_size = int(total * val_ratio)
    
    train_pairs = pairs[:train_size]
    val_pairs = pairs[train_size:train_size + val_size]
    test_pairs = pairs[train_size + val_size:]
    
    print(f"   ✅ Train: {len(train_pairs)} samples")
    print(f"   ✅ Val: {len(val_pairs)} samples")
    if test_pairs:
        print(f"   ✅ Test: {len(test_pairs)} samples")
    
    return {
        'train': train_pairs,
        'val': val_pairs,
        'test': test_pairs
    }


def copy_files(splits, output_dir):
    """Copy files to organized structure"""
    
    print(f"\n📋 Copying files to {output_dir}...")
    
    for split_name, pairs in splits.items():
        if not pairs:
            continue
        
        print(f"\n   Processing {split_name} set...")
        
        for i, (img_path, label_path) in enumerate(pairs, 1):
            # Copy image
            img_dest = f"{output_dir}/images/{split_name}/{img_path.name}"
            shutil.copy2(img_path, img_dest)
            
            # Copy label
            label_dest = f"{output_dir}/labels/{split_name}/{label_path.name}"
            shutil.copy2(label_path, label_dest)
            
            if i % 100 == 0:
                print(f"      Copied {i}/{len(pairs)}...")
        
        print(f"   ✅ Completed {split_name}: {len(pairs)} files")


def verify_dataset(output_dir):
    """Verify the organized dataset"""
    
    print(f"\n🔍 Verifying dataset structure...")
    
    issues = []
    
    for split in ['train', 'val']:
        img_dir = f"{output_dir}/images/{split}"
        label_dir = f"{output_dir}/labels/{split}"
        
        images = list(Path(img_dir).glob('*.*'))
        labels = list(Path(label_dir).glob('*.txt'))
        
        print(f"\n   {split.upper()}:")
        print(f"      Images: {len(images)}")
        print(f"      Labels: {len(labels)}")
        
        if len(images) != len(labels):
            issues.append(f"{split}: Mismatch - {len(images)} images vs {len(labels)} labels")
        
        # Verify some labels
        print(f"      Verifying label format...")
        error_count = 0
        for label_path in labels[:min(10, len(labels))]:
            valid, msg = verify_label_format(label_path)
            if not valid:
                error_count += 1
                issues.append(f"{split}/{label_path.name}: {msg}")
        
        if error_count == 0:
            print(f"      ✅ Labels verified")
        else:
            print(f"      ⚠️  Found {error_count} label errors")
    
    if issues:
        print(f"\n⚠️  ISSUES FOUND:")
        for issue in issues:
            print(f"   - {issue}")
        return False
    else:
        print(f"\n✅ Dataset verified successfully!")
        return True


def create_data_yaml(output_dir, num_classes=1, class_names=['face']):
    """Create data.yaml configuration file"""
    
    yaml_path = f"{output_dir}/data.yaml"
    
    content = f"""# YOLOv8 Face Detection Dataset Configuration

path: {os.path.abspath(output_dir)}
train: images/train
val: images/val
test: images/test

nc: {num_classes}
names: {class_names}
"""
    
    with open(yaml_path, 'w') as f:
        f.write(content)
    
    print(f"\n📄 Created: {yaml_path}")


def print_summary(output_dir):
    """Print dataset summary"""
    
    print("\n" + "="*60)
    print("   DATASET PREPARATION COMPLETE")
    print("="*60)
    
    print(f"\n📁 Dataset location: {os.path.abspath(output_dir)}")
    print(f"📄 Configuration: {os.path.abspath(output_dir)}/data.yaml")
    
    print("\n📊 Dataset structure:")
    for split in ['train', 'val', 'test']:
        img_count = len(list(Path(f"{output_dir}/images/{split}").glob('*.*')))
        if img_count > 0:
            print(f"   {split.capitalize()}: {img_count} images")
    
    print("\n🚀 Next steps:")
    print("   1. Verify the dataset visually")
    print("   2. Update train_yolov8_face.py with correct data.yaml path")
    print("   3. Run: python train_yolov8_face.py --mode train")
    
    print("\n" + "="*60 + "\n")


# ==================== MAIN ====================

def main():
    """Main dataset preparation workflow"""
    
    print("\n" + "="*60)
    print("   YOLOv8 DATASET PREPARATION TOOL")
    print("="*60)
    
    # Check source directories
    if not os.path.exists(SOURCE_IMAGES):
        print(f"\n❌ ERROR: {SOURCE_IMAGES} directory not found!")
        print(f"   Please create it and add your images")
        return
    
    if not os.path.exists(SOURCE_LABELS):
        print(f"\n❌ ERROR: {SOURCE_LABELS} directory not found!")
        print(f"   Please create it and add your label files")
        return
    
    # Step 1: Create directory structure
    create_directory_structure(OUTPUT_DIR)
    
    # Step 2: Find image-label pairs
    pairs = get_image_label_pairs(SOURCE_IMAGES, SOURCE_LABELS)
    
    if len(pairs) == 0:
        print("\n❌ ERROR: No valid image-label pairs found!")
        return
    
    # Step 3: Split dataset
    splits = split_dataset(pairs, TRAIN_RATIO, VAL_RATIO, RANDOM_SEED)
    
    # Step 4: Copy files
    copy_files(splits, OUTPUT_DIR)
    
    # Step 5: Verify dataset
    verify_dataset(OUTPUT_DIR)
    
    # Step 6: Create data.yaml
    create_data_yaml(OUTPUT_DIR)
    
    # Step 7: Print summary
    print_summary(OUTPUT_DIR)


if __name__ == "__main__":
    main()
