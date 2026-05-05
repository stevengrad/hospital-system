"""
LABEL FORMAT CONVERTER
Convert various annotation formats to YOLO format for face detection
"""

import os
import json
import xml.etree.ElementTree as ET
from pathlib import Path

# ==================== CONVERSION FUNCTIONS ====================

def convert_coco_to_yolo(coco_json_path, images_dir, output_dir):
    """
    Convert COCO format annotations to YOLO format
    
    COCO format: {id, image_id, category_id, bbox: [x, y, width, height]}
    YOLO format: class x_center y_center width height (all normalized)
    """
    
    print(f"\n🔄 Converting COCO to YOLO format...")
    
    # Load COCO JSON
    with open(coco_json_path, 'r') as f:
        coco_data = json.load(f)
    
    # Create output directory
    os.makedirs(output_dir, exist_ok=True)
    
    # Build image id to filename mapping
    image_info = {img['id']: img for img in coco_data['images']}
    
    # Group annotations by image
    annotations_by_image = {}
    for ann in coco_data['annotations']:
        img_id = ann['image_id']
        if img_id not in annotations_by_image:
            annotations_by_image[img_id] = []
        annotations_by_image[img_id].append(ann)
    
    # Convert each image's annotations
    converted = 0
    for img_id, annotations in annotations_by_image.items():
        img_info = image_info[img_id]
        img_width = img_info['width']
        img_height = img_info['height']
        img_filename = img_info['file_name']
        
        # Create YOLO label file
        label_filename = Path(img_filename).stem + '.txt'
        label_path = os.path.join(output_dir, label_filename)
        
        with open(label_path, 'w') as f:
            for ann in annotations:
                # COCO bbox: [x, y, width, height] (absolute)
                x, y, w, h = ann['bbox']
                
                # Convert to YOLO format (normalized)
                x_center = (x + w / 2) / img_width
                y_center = (y + h / 2) / img_height
                width = w / img_width
                height = h / img_height
                
                # Class is 0 for face (single class)
                class_id = 0
                
                # Write YOLO format
                f.write(f"{class_id} {x_center:.6f} {y_center:.6f} {width:.6f} {height:.6f}\n")
        
        converted += 1
    
    print(f"   ✅ Converted {converted} annotations")


def convert_pascal_voc_to_yolo(xml_dir, output_dir):
    """
    Convert Pascal VOC XML format to YOLO format
    
    VOC format: XML with <bndbox><xmin>, <ymin>, <xmax>, <ymax></bndbox>
    YOLO format: class x_center y_center width height (all normalized)
    """
    
    print(f"\n🔄 Converting Pascal VOC to YOLO format...")
    
    os.makedirs(output_dir, exist_ok=True)
    
    xml_files = list(Path(xml_dir).glob('*.xml'))
    converted = 0
    
    for xml_file in xml_files:
        tree = ET.parse(xml_file)
        root = tree.getroot()
        
        # Get image dimensions
        size = root.find('size')
        img_width = int(size.find('width').text)
        img_height = int(size.find('height').text)
        
        # Create YOLO label file
        label_filename = xml_file.stem + '.txt'
        label_path = os.path.join(output_dir, label_filename)
        
        with open(label_path, 'w') as f:
            # Process each object (face)
            for obj in root.findall('object'):
                bndbox = obj.find('bndbox')
                xmin = int(bndbox.find('xmin').text)
                ymin = int(bndbox.find('ymin').text)
                xmax = int(bndbox.find('xmax').text)
                ymax = int(bndbox.find('ymax').text)
                
                # Convert to YOLO format
                x_center = ((xmin + xmax) / 2) / img_width
                y_center = ((ymin + ymax) / 2) / img_height
                width = (xmax - xmin) / img_width
                height = (ymax - ymin) / img_height
                
                class_id = 0  # face class
                
                f.write(f"{class_id} {x_center:.6f} {y_center:.6f} {width:.6f} {height:.6f}\n")
        
        converted += 1
    
    print(f"   ✅ Converted {converted} XML files")


def convert_csv_to_yolo(csv_path, output_dir):
    """
    Convert CSV format to YOLO format
    
    CSV format: filename,xmin,ymin,xmax,ymax,width,height
    YOLO format: class x_center y_center width height (all normalized)
    """
    
    print(f"\n🔄 Converting CSV to YOLO format...")
    
    os.makedirs(output_dir, exist_ok=True)
    
    import csv
    
    # Group by filename
    annotations = {}
    
    with open(csv_path, 'r') as f:
        reader = csv.DictReader(f)
        for row in reader:
            filename = row['filename']
            if filename not in annotations:
                annotations[filename] = {
                    'width': int(row['width']),
                    'height': int(row['height']),
                    'boxes': []
                }
            
            annotations[filename]['boxes'].append({
                'xmin': int(row['xmin']),
                'ymin': int(row['ymin']),
                'xmax': int(row['xmax']),
                'ymax': int(row['ymax'])
            })
    
    # Convert each file
    converted = 0
    for filename, data in annotations.items():
        label_filename = Path(filename).stem + '.txt'
        label_path = os.path.join(output_dir, label_filename)
        
        img_width = data['width']
        img_height = data['height']
        
        with open(label_path, 'w') as f:
            for box in data['boxes']:
                # Convert to YOLO format
                x_center = ((box['xmin'] + box['xmax']) / 2) / img_width
                y_center = ((box['ymin'] + box['ymax']) / 2) / img_height
                width = (box['xmax'] - box['xmin']) / img_width
                height = (box['ymax'] - box['ymin']) / img_height
                
                class_id = 0
                
                f.write(f"{class_id} {x_center:.6f} {y_center:.6f} {width:.6f} {height:.6f}\n")
        
        converted += 1
    
    print(f"   ✅ Converted {converted} files")


def verify_yolo_labels(labels_dir):
    """Verify YOLO format labels"""
    
    print(f"\n🔍 Verifying YOLO labels in {labels_dir}...")
    
    label_files = list(Path(labels_dir).glob('*.txt'))
    errors = []
    
    for label_file in label_files:
        with open(label_file, 'r') as f:
            lines = f.readlines()
        
        for i, line in enumerate(lines, 1):
            parts = line.strip().split()
            
            if len(parts) != 5:
                errors.append(f"{label_file.name}:{i} - Wrong format (need 5 values)")
                continue
            
            try:
                class_id = int(parts[0])
                x, y, w, h = map(float, parts[1:])
                
                # Check normalization
                if not (0 <= x <= 1 and 0 <= y <= 1 and 0 <= w <= 1 and 0 <= h <= 1):
                    errors.append(f"{label_file.name}:{i} - Values not in range [0,1]")
                
            except ValueError:
                errors.append(f"{label_file.name}:{i} - Invalid numeric values")
    
    if errors:
        print(f"\n⚠️  Found {len(errors)} errors:")
        for error in errors[:10]:  # Show first 10
            print(f"   - {error}")
        if len(errors) > 10:
            print(f"   ... and {len(errors) - 10} more")
        return False
    else:
        print(f"   ✅ All {len(label_files)} labels verified successfully!")
        return True


# ==================== EXAMPLE USAGE ====================

def example_usage():
    """Show examples of how to use the converters"""
    
    print("\n" + "="*60)
    print("   LABEL FORMAT CONVERTER - EXAMPLES")
    print("="*60)
    
    print("\n1. Convert COCO to YOLO:")
    print("   convert_coco_to_yolo(")
    print("       'annotations.json',")
    print("       'images/',")
    print("       'yolo_labels/'")
    print("   )")
    
    print("\n2. Convert Pascal VOC to YOLO:")
    print("   convert_pascal_voc_to_yolo(")
    print("       'voc_annotations/',")
    print("       'yolo_labels/'")
    print("   )")
    
    print("\n3. Convert CSV to YOLO:")
    print("   convert_csv_to_yolo(")
    print("       'annotations.csv',")
    print("       'yolo_labels/'")
    print("   )")
    
    print("\n4. Verify YOLO labels:")
    print("   verify_yolo_labels('yolo_labels/')")
    
    print("\n" + "="*60 + "\n")


# ==================== MAIN ====================

if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Convert annotation formats to YOLO")
    parser.add_argument('--format', type=str, required=True,
                       choices=['coco', 'voc', 'csv'],
                       help='Source format: coco, voc, or csv')
    parser.add_argument('--input', type=str, required=True,
                       help='Input file/directory path')
    parser.add_argument('--output', type=str, default='yolo_labels',
                       help='Output directory for YOLO labels')
    parser.add_argument('--images', type=str, default=None,
                       help='Images directory (for COCO format)')
    parser.add_argument('--verify', action='store_true',
                       help='Verify output labels')
    
    args = parser.parse_args()
    
    # Convert based on format
    if args.format == 'coco':
        if args.images is None:
            print("❌ ERROR: --images required for COCO format")
        else:
            convert_coco_to_yolo(args.input, args.images, args.output)
    
    elif args.format == 'voc':
        convert_pascal_voc_to_yolo(args.input, args.output)
    
    elif args.format == 'csv':
        convert_csv_to_yolo(args.input, args.output)
    
    # Verify if requested
    if args.verify:
        verify_yolo_labels(args.output)
    
    print("\n✅ Conversion complete!")
    print(f"   Output: {args.output}")
