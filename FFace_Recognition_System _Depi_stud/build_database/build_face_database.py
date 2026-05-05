"""
SIMPLE FACE DATABASE BUILDER
Capture face photos from webcam - Simple version
Just run, enter name, capture photos, done!
Press 'q' when you have enough photos
"""

import cv2
import os
from datetime import datetime
import numpy as np
from ultralytics import YOLO

# ==================== CONFIGURATION ====================

OUTPUT_DIR = "db_folder"
FACE_DETECTOR = YOLO("yolov8l_100e.pt")

# ==================== FUNCTIONS ====================

def detect_face(frame):
    """Detect largest face in frame"""
    results = FACE_DETECTOR.predict(frame, conf=0.5, verbose=False)
    
    if len(results) == 0 or results[0].boxes is None or len(results[0].boxes) == 0:
        return False, None, None
    
    # Get largest face
    boxes = results[0].boxes.xyxy.cpu().numpy()
    largest_idx = max(range(len(boxes)), key=lambda i: (boxes[i][2]-boxes[i][0])*(boxes[i][3]-boxes[i][1]))
    x1, y1, x2, y2 = map(int, boxes[largest_idx])
    
    face_roi = frame[y1:y2, x1:x2]
    return True, (x1, y1, x2, y2), face_roi


def capture_photos(person_name):
    """Capture photos for one person"""
    
    # Create person folder
    person_folder = os.path.join(OUTPUT_DIR, person_name)
    os.makedirs(person_folder, exist_ok=True)
    
    # Open camera
    camera = cv2.VideoCapture(0)
    if not camera.isOpened():
        print("❌ ERROR: Cannot open camera!")
        return False
    
    camera.set(cv2.CAP_PROP_FRAME_WIDTH, 1280)
    camera.set(cv2.CAP_PROP_FRAME_HEIGHT, 720)
    
    photos_captured = 0
    last_capture_time = 0
    
    print(f"\n📸 Starting photo capture for: {person_name}")
    print("Position your face in the green box")
    print("Photos will capture automatically every 3 seconds")
    print("Press 'q' when you have enough photos\n")
    
    while True:
        ret, frame = camera.read()
        if not ret:
            continue
        
        frame = cv2.flip(frame, 1)
        display_frame = frame.copy()
        
        # Detect face
        face_found, bbox, face_roi = detect_face(frame)
        
        if face_found:
            x1, y1, x2, y2 = bbox
            
            # Draw green box around face
            cv2.rectangle(display_frame, (x1, y1), (x2, y2), (0, 255, 0), 3)
            
            # Auto-capture every 3 seconds
            current_time = cv2.getTickCount() / cv2.getTickFrequency()
            
            if current_time - last_capture_time > 3:
                # Save photo
                timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                filename = f"{person_name}_{photos_captured+1:03d}_{timestamp}.jpg"
                filepath = os.path.join(person_folder, filename)
                
                cv2.imwrite(filepath, face_roi)
                photos_captured += 1
                last_capture_time = current_time
                
                print(f"   ✅ Photo {photos_captured} saved!")
            
            # Status text
            cv2.putText(display_frame, "Ready - Capturing...", (x1, y1-10),
                       cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2)
        
        else:
            # No face detected
            cv2.putText(display_frame, "No face detected!", (20, 50),
                       cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 2)
        
        # Show photo count
        progress_text = f"Photos captured: {photos_captured}"
        cv2.putText(display_frame, progress_text, (20, display_frame.shape[0] - 20),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
        
        # Display frame
        cv2.imshow("Face Capture", display_frame)
        
        # Check for quit
        if cv2.waitKey(1) & 0xFF == ord('q'):
            break
    
    camera.release()
    cv2.destroyAllWindows()
    
    print(f"\n✅ Captured {photos_captured} photos total!")
    return True


# ==================== MAIN ====================

def main():
    print("\n" + "="*60)
    print("   FACE DATABASE BUILDER")
    print("="*60)
    
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    
    # Get person name
    person_name = input("\nEnter person's name: ").strip()
    
    if not person_name:
        print("❌ Name cannot be empty!")
        return
    
    # Capture photos
    success = capture_photos(person_name)
    
    if success:
        print(f"\n✅ Done! Photos saved to: {OUTPUT_DIR}/{person_name}/")
        print("\n🚀 Next: Run face recognition")
        print("   python face_recognition.py")
    
    print("\n" + "="*60 + "\n")


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n\n⚠️  Interrupted")
        cv2.destroyAllWindows()
    except Exception as e:
        print(f"\n❌ ERROR: {e}")