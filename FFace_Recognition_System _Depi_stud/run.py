"""
    FACE RECOGNITION SYSTEM - USING YOLOv8-Face
    - YOLOv8-Face (large) for face detection
    - FaceNet for recognition
    - Simple, modern, and efficient
"""
import cv2
import os
import pickle
import numpy as np
from numpy import asarray
import torch
from facenet_pytorch import InceptionResnetV1
from ultralytics import YOLO

# ==================== CONFIGURATION ====================
DB_FOLDER = "db_folder/"
OUTPUT_FOLDER = "detected_faces"
RECOGNITION_THRESHOLD = 0.6  # Lower = stricter matching
DETECTION_CONFIDENCE = 0.3   # YOLO confidence threshold

os.makedirs(OUTPUT_FOLDER, exist_ok=True)

# ==================== INITIALIZE MODELS ====================
print("Loading models...")

# Face Detection - YOLOv8-Face Large
# Download weights from: https://drive.google.com/file/d/1iHL-XjvzpbrE8ycVqEbGla4yc1dWlSWU/view?usp=sharing
# Save as 'yolov8l_face.pt' in the same directory
face_detector = YOLO("yolov8l_100e.pt")

# Face Recognition - FaceNet
face_recognizer = InceptionResnetV1(pretrained='vggface2').eval()

print("Models loaded successfully!")

# ==================== HELPER FUNCTIONS ====================
def detect_faces(frame):
    """
    Detect faces in frame using YOLOv8-Face
    Returns: list of bounding boxes [[x1, y1, x2, y2], ...]
    """
    # Run YOLO detection
    results = face_detector.predict(
        frame, 
        conf=DETECTION_CONFIDENCE,
        verbose=False  # Suppress output
    )
    
    # Extract bounding boxes
    bboxes = []
    if len(results) > 0 and results[0].boxes is not None:
        boxes = results[0].boxes.xyxy.cpu().numpy()  # x1, y1, x2, y2
        for box in boxes:
            x1, y1, x2, y2 = map(int, box)
            bboxes.append([x1, y1, x2, y2])
    
    return bboxes


def preprocess_face(face_img):
    """
    Preprocess face image for FaceNet
    Input: BGR image from OpenCV
    Output: PyTorch tensor ready for FaceNet
    """
    # Convert BGR to RGB
    face_rgb = cv2.cvtColor(face_img, cv2.COLOR_BGR2RGB)
    
    # Resize to 160x160 (FaceNet input size)
    face_resized = cv2.resize(face_rgb, (160, 160))
    face_pixels = asarray(face_resized)
    
    # Normalize pixels
    face_pixels = face_pixels.astype('float32')
    mean, std = face_pixels.mean(), face_pixels.std()
    face_pixels = (face_pixels - mean) / std
    
    # Convert to PyTorch tensor: (H, W, C) -> (C, H, W)
    face_tensor = torch.from_numpy(face_pixels.transpose((2, 0, 1))).float()
    
    # Add batch dimension: (C, H, W) -> (1, C, H, W)
    face_tensor = face_tensor.unsqueeze(0)
    
    return face_tensor


def extract_embedding(face_tensor):
    """
    Extract face embedding using FaceNet
    Returns: 512-dimensional normalized embedding vector
    """
    with torch.no_grad():
        embedding = face_recognizer(face_tensor)
    
    # Convert to numpy and L2 normalize
    embedding = embedding.detach().numpy()
    embedding = embedding / np.sqrt(np.sum(np.multiply(embedding, embedding)))
    
    return embedding


def calculate_distance(embedding1, embedding2):
    """
    Calculate Euclidean distance between two embeddings
    Lower distance = more similar faces
    """
    return np.linalg.norm(embedding1 - embedding2)


def load_or_create_database():
    """
    Load existing face database or create new one from db_folder/
    
    Database format: [[person_name, embedding], [person_name, embedding], ...]
    """
    db_file = os.path.join(DB_FOLDER, "representations.pkl")
    
    # Try to load existing database
    if os.path.exists(db_file):
        print(f"Loading existing database from {db_file}")
        with open(db_file, 'rb') as f:
            database = pickle.load(f)
        print(f"   Database contains {len(database)} face embeddings")
        return database
    
    # Create new database
    print(f"Creating new database from {DB_FOLDER}")
    database = []
    
    # Check if db_folder exists
    if not os.path.exists(DB_FOLDER):
        print(f"ERROR: {DB_FOLDER} does not exist!")
        print(f"   Please create it and add person subfolders with images")
        return []
    
    # Scan db_folder for person subfolders
    person_folders = [f for f in os.listdir(DB_FOLDER) 
                     if os.path.isdir(os.path.join(DB_FOLDER, f))]
    
    if len(person_folders) == 0:
        print(f"ERROR: No person folders found in {DB_FOLDER}")
        return []
    
    print(f"   Found {len(person_folders)} person folders")
    
    # Process each person
    for person_name in person_folders:
        person_path = os.path.join(DB_FOLDER, person_name)
        print(f"\n Processing {person_name}...")
        
        # Get all image files
        image_files = [f for f in os.listdir(person_path)
                      if f.lower().endswith(('.jpg', '.jpeg', '.png', '.bmp'))]
        
        if len(image_files) == 0:
            print(f"No images found for {person_name}")
            continue
        
        # Process each image
        for img_file in image_files:
            img_path = os.path.join(person_path, img_file)
            
            # Load image
            img = cv2.imread(img_path)
            if img is None:
                print(f"Could not load {img_file}")
                continue
            
            try:
                # Preprocess and extract embedding
                face_tensor = preprocess_face(img)
                embedding = extract_embedding(face_tensor)
                
                # Add to database
                database.append([person_name, embedding])
                print(f"Added {img_file}")
                
            except Exception as e:
                print(f"Error processing {img_file}: {e}")
    
    # Save database
    if len(database) > 0:
        with open(db_file, 'wb') as f:
            pickle.dump(database, f)
        print(f"\nDatabase saved with {len(database)} embeddings!")
    else:
        print(f"\nNo faces were processed!")
    
    return database


def recognize_face(face_img, database):
    """
    Recognize a face by comparing with database
    
    Args:
        face_img: Cropped face image (BGR format)
        database: List of [person_name, embedding] pairs
    
    Returns:
        (identity, distance): Person's name and similarity score
    """
    # Extract embedding from face
    face_tensor = preprocess_face(face_img)
    face_embedding = extract_embedding(face_tensor)
    
    # Compare with all database embeddings
    min_distance = float('inf')
    identity = "unknown"
    
    for person_name, db_embedding in database:
        distance = calculate_distance(face_embedding, db_embedding)
        
        if distance < min_distance:
            min_distance = distance
            identity = person_name
    
    # Check if below threshold
    if min_distance > RECOGNITION_THRESHOLD:
        identity = "unknown"
    
    return identity, min_distance


# ==================== MAIN PROGRAM ====================

def main():
    """
    Main face recognition loop
    """
    print("\n" + "="*60)
    print("   FACE RECOGNITION SYSTEM - YOLOv8-Face + FaceNet")
    print("="*60 + "\n")
    
    # Load or create face database
    database = load_or_create_database()
    
    if len(database) == 0:
        print("\nERROR: Empty database!")
        print(f"Please add person folders with images to {DB_FOLDER}")
        print("\nExample structure:")
        print("  db_folder/")
        print(" person1/")
        print(" photo1.jpg")
        print(" photo2.jpg")
        print(" person2/")
        print("     photo1.jpg")
        return
    
    # Open camera (0 = webcam, or use video file path)
    print("\n Opening camera...")
    camera = cv2.VideoCapture(0)
    
    if not camera.isOpened():
        print("ERROR: Could not open camera!")
        print("   Try changing camera index (0, 1, 2...) or use video file")
        return
    
    print("\n" + "="*60)
    print("   SYSTEM READY - Face Recognition Started!")
    print("="*60)
    print(f"   Database: {len(database)} face embeddings")
    print(f"   Recognition threshold: {RECOGNITION_THRESHOLD}")
    print(f"   Detection confidence: {DETECTION_CONFIDENCE}")
    print(f"\n   Press 'q' to quit")
    print(f"   Press 's' to save current frame")
    print("="*60 + "\n")
    
    frame_count = 0
    saved_count = 0
    
    while True:
        ret, frame = camera.read()
        
        if not ret or frame is None:
            print("Failed to grab frame")
            continue
        
        # Detect faces using YOLOv8-Face
        bboxes = detect_faces(frame)
        
        # Process each detected face
        for bbox in bboxes:
            x1, y1, x2, y2 = bbox
            
            # Ensure valid coordinates
            x1 = max(0, x1)
            y1 = max(0, y1)
            x2 = min(frame.shape[1], x2)
            y2 = min(frame.shape[0], y2)
            
            # Skip if box is too small
            if (x2 - x1) < 30 or (y2 - y1) < 30:
                continue
            
            # Crop face region
            face_img = frame[y1:y2, x1:x2]
            
            if face_img.size == 0:
                continue
            
            try:
                # Recognize face
                identity, distance = recognize_face(face_img, database)
                
                # Set color and text based on recognition
                if identity == "unknown":
                    color = (0, 0, 255)  # Red for unknown
                    text = f"Unknown ({distance:.2f})"
                else:
                    color = (0, 255, 0)  # Green for known
                    text = f"{identity} ({distance:.2f})"
                
                # Draw bounding box
                cv2.rectangle(frame, (x1, y1), (x2, y2), color, 2)
                
                # Draw label background
                label_size, _ = cv2.getTextSize(text, cv2.FONT_HERSHEY_SIMPLEX, 0.6, 2)
                cv2.rectangle(frame, (x1, y1 - 30), (x1 + label_size[0], y1), color, -1)
                
                # Draw label text
                cv2.putText(frame, text, (x1, y1 - 10), 
                           cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)
                
            except Exception as e:
                print(f"Error recognizing face: {e}")
        
        # Add frame counter
        cv2.putText(frame, f"Frame: {frame_count}", (10, 30),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
        
        # Display frame
        cv2.imshow("Face Recognition - YOLOv8-Face", frame)
        frame_count += 1
        
        # Handle keyboard input
        key = cv2.waitKey(1) & 0xFF
        
        if key == ord('q'):
            # Quit
            break
        elif key == ord('s'):
            # Save frame
            save_path = os.path.join(OUTPUT_FOLDER, f"frame_{saved_count}.jpg")
            cv2.imwrite(save_path, frame)
            print(f"Saved frame to {save_path}")
            saved_count += 1
    
    # Cleanup
    camera.release()
    cv2.destroyAllWindows()
    
    print("\n" + "="*60)
    print(f"   Session Complete!")
    print("="*60)
    print(f"   Processed {frame_count} frames")
    print(f"   Saved {saved_count} frames")
    print("\n   Goodbye!\n")


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n Interrupted by user")
        cv2.destroyAllWindows()
    except Exception as e:
        print(f"\n ERROR: {e}")
        import traceback
        traceback.print_exc()