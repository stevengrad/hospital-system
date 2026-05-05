"""
FACE RECOGNITION TRAINING - TRIPLET LOSS
Train a custom face recognition model from scratch using triplet loss
This script teaches the model to create unique embeddings for each person
"""

import torch
import torch.nn as nn
import torch.optim as optim
from torch.utils.data import Dataset, DataLoader
from torchvision import transforms, models
from PIL import Image
import os
import numpy as np
from tqdm import tqdm
import random
import itertools
from collections import defaultdict

# ==================== CONFIGURATION ====================

# Dataset
TRAIN_DIR = "train_faces"          # Folder with person subfolders
VAL_DIR = "val_faces"              # Validation folder (optional)
TRAIN_RATIO = 0.8                  # If no val folder, split from train

# Model architecture
BACKBONE = "resnet50"              # resnet18, resnet50, mobilenet
EMBEDDING_DIM = 512                # Size of embedding vector
PRETRAINED = True                  # Use ImageNet weights

# Triplet loss
MARGIN = 0.5                       # Margin for triplet loss
MINING_STRATEGY = "hard"           # hard, semi-hard, all

# Training
BATCH_SIZE = 32                    # Triplets per batch
NUM_EPOCHS = 100                   # Training epochs
LEARNING_RATE = 0.001              # Initial LR
WEIGHT_DECAY = 0.0005              # L2 regularization

# Data
IMAGE_SIZE = 160                   # Input image size
AUGMENTATION = True                # Enable augmentation

# Saving
SAVE_DIR = "trained_model"
SAVE_EVERY = 10                    # Save checkpoint every N epochs
DEVICE = "cuda" if torch.cuda.is_available() else "cpu"

print(f"Using device: {DEVICE}")

# ==================== DATASET CLASS ====================

class TripletFaceDataset(Dataset):
    """
    Dataset for triplet loss training
    Each sample returns (anchor, positive, negative)
    """
    
    def __init__(self, root_dir, transform=None, triplets_per_epoch=10000):
        """
        Args:
            root_dir: Directory with person subfolders
            transform: Image transformations
            triplets_per_epoch: Number of triplets to generate per epoch
        """
        self.root_dir = root_dir
        self.transform = transform
        self.triplets_per_epoch = triplets_per_epoch
        
        # Build dataset structure
        self.person_to_images = defaultdict(list)
        self.all_people = []
        
        # Scan directories
        for person_name in os.listdir(root_dir):
            person_path = os.path.join(root_dir, person_name)
            if not os.path.isdir(person_path):
                continue
            
            image_files = [f for f in os.listdir(person_path)
                          if f.lower().endswith(('.jpg', '.jpeg', '.png'))]
            
            if len(image_files) >= 2:  # Need at least 2 images for positive pairs
                self.all_people.append(person_name)
                for img_file in image_files:
                    img_path = os.path.join(person_path, img_file)
                    self.person_to_images[person_name].append(img_path)
        
        print(f"Loaded {len(self.all_people)} people with {sum(len(imgs) for imgs in self.person_to_images.values())} images")
        
        # Pre-generate triplets
        self.triplets = self._generate_triplets()
    
    def _generate_triplets(self):
        """Generate triplets for one epoch"""
        triplets = []
        
        for _ in range(self.triplets_per_epoch):
            # Select anchor person
            anchor_person = random.choice(self.all_people)
            
            # Select anchor and positive from same person
            if len(self.person_to_images[anchor_person]) < 2:
                continue
            
            anchor_img, positive_img = random.sample(
                self.person_to_images[anchor_person], 2
            )
            
            # Select negative from different person
            negative_person = random.choice(
                [p for p in self.all_people if p != anchor_person]
            )
            negative_img = random.choice(self.person_to_images[negative_person])
            
            triplets.append((anchor_img, positive_img, negative_img))
        
        return triplets
    
    def __len__(self):
        return len(self.triplets)
    
    def __getitem__(self, idx):
        anchor_path, positive_path, negative_path = self.triplets[idx]
        
        # Load images
        anchor = Image.open(anchor_path).convert('RGB')
        positive = Image.open(positive_path).convert('RGB')
        negative = Image.open(negative_path).convert('RGB')
        
        # Apply transforms
        if self.transform:
            anchor = self.transform(anchor)
            positive = self.transform(positive)
            negative = self.transform(negative)
        
        return anchor, positive, negative


# ==================== MODEL ====================

class EmbeddingNet(nn.Module):
    """
    Embedding network for face recognition
    Takes an image and outputs an embedding vector
    """
    
    def __init__(self, backbone='resnet50', embedding_dim=512, pretrained=True):
        super(EmbeddingNet, self).__init__()
        
        # Load backbone
        if backbone == 'resnet18':
            self.backbone = models.resnet18(pretrained=pretrained)
            feat_dim = 512
        elif backbone == 'resnet50':
            self.backbone = models.resnet50(pretrained=pretrained)
            feat_dim = 2048
        elif backbone == 'resnet101':
            self.backbone = models.resnet101(pretrained=pretrained)
            feat_dim = 2048
        elif backbone == 'mobilenet':
            self.backbone = models.mobilenet_v2(pretrained=pretrained)
            feat_dim = 1280
        else:
            raise ValueError(f"Unknown backbone: {backbone}")
        
        # Remove final classification layer
        if 'resnet' in backbone:
            self.backbone = nn.Sequential(*list(self.backbone.children())[:-1])
        else:  # mobilenet
            self.backbone.classifier = nn.Identity()
        
        # Embedding layer
        self.embedding = nn.Sequential(
            nn.Linear(feat_dim, embedding_dim),
            nn.BatchNorm1d(embedding_dim)
        )
    
    def forward(self, x):
        # Extract features
        features = self.backbone(x)
        features = features.view(features.size(0), -1)
        
        # Get embeddings
        embeddings = self.embedding(features)
        
        # L2 normalize
        embeddings = nn.functional.normalize(embeddings, p=2, dim=1)
        
        return embeddings


# ==================== TRIPLET LOSS ====================

class TripletLoss(nn.Module):
    """
    Triplet loss: L = max(d(a,p) - d(a,n) + margin, 0)
    """
    
    def __init__(self, margin=0.5):
        super(TripletLoss, self).__init__()
        self.margin = margin
    
    def forward(self, anchor, positive, negative):
        """
        Args:
            anchor: Embeddings of anchor samples (batch_size, embedding_dim)
            positive: Embeddings of positive samples
            negative: Embeddings of negative samples
        """
        # Calculate distances
        pos_dist = torch.sum((anchor - positive) ** 2, dim=1)  # ||a - p||^2
        neg_dist = torch.sum((anchor - negative) ** 2, dim=1)  # ||a - n||^2
        
        # Triplet loss
        losses = torch.relu(pos_dist - neg_dist + self.margin)
        
        return losses.mean()


class HardTripletLoss(nn.Module):
    """
    Hard triplet loss - mines hard triplets from batch
    """
    
    def __init__(self, margin=0.5, mining='hard'):
        super(HardTripletLoss, self).__init__()
        self.margin = margin
        self.mining = mining
    
    def forward(self, embeddings, labels):
        """
        Args:
            embeddings: (batch_size, embedding_dim)
            labels: (batch_size,) - person IDs
        """
        # Calculate pairwise distances
        pairwise_dist = torch.cdist(embeddings, embeddings, p=2)
        
        # For each anchor
        loss = 0
        num_triplets = 0
        
        for i in range(len(embeddings)):
            anchor_label = labels[i]
            
            # Positive mask (same person, different image)
            pos_mask = (labels == anchor_label)
            pos_mask[i] = False  # Exclude anchor itself
            
            # Negative mask (different person)
            neg_mask = (labels != anchor_label)
            
            if not pos_mask.any() or not neg_mask.any():
                continue
            
            # Get distances
            pos_dists = pairwise_dist[i][pos_mask]
            neg_dists = pairwise_dist[i][neg_mask]
            
            if self.mining == 'hard':
                # Hardest positive (farthest same person)
                hardest_pos_dist = pos_dists.max()
                # Hardest negative (closest different person)
                hardest_neg_dist = neg_dists.min()
                
                loss += torch.relu(hardest_pos_dist - hardest_neg_dist + self.margin)
                num_triplets += 1
            
            elif self.mining == 'semi-hard':
                # Semi-hard: negative is farther than positive but within margin
                for pos_dist in pos_dists:
                    semi_hard_negs = neg_dists[
                        (neg_dists > pos_dist) & 
                        (neg_dists < pos_dist + self.margin)
                    ]
                    if len(semi_hard_negs) > 0:
                        loss += torch.relu(pos_dist - semi_hard_negs.min() + self.margin)
                        num_triplets += 1
            
            else:  # all
                # All valid triplets
                for pos_dist in pos_dists:
                    for neg_dist in neg_dists:
                        loss += torch.relu(pos_dist - neg_dist + self.margin)
                        num_triplets += 1
        
        return loss / max(num_triplets, 1)


# ==================== TRAINING FUNCTIONS ====================

def train_epoch(model, dataloader, criterion, optimizer, device):
    """Train for one epoch"""
    
    model.train()
    total_loss = 0
    
    pbar = tqdm(dataloader, desc="Training")
    for anchors, positives, negatives in pbar:
        # Move to device
        anchors = anchors.to(device)
        positives = positives.to(device)
        negatives = negatives.to(device)
        
        # Forward pass
        anchor_embeddings = model(anchors)
        positive_embeddings = model(positives)
        negative_embeddings = model(negatives)
        
        # Calculate loss
        loss = criterion(anchor_embeddings, positive_embeddings, negative_embeddings)
        
        # Backward pass
        optimizer.zero_grad()
        loss.backward()
        optimizer.step()
        
        # Update progress
        total_loss += loss.item()
        pbar.set_postfix({'loss': f'{loss.item():.4f}'})
    
    return total_loss / len(dataloader)


def validate(model, val_loader, device):
    """
    Validate using 1-NN classifier
    For each embedding, find nearest neighbor and check if same person
    """
    
    model.eval()
    all_embeddings = []
    all_labels = []
    all_paths = []
    
    # Extract all embeddings
    with torch.no_grad():
        for batch in tqdm(val_loader, desc="Extracting embeddings"):
            images = batch[0].to(device) if isinstance(batch, tuple) else batch.to(device)
            embeddings = model(images)
            all_embeddings.append(embeddings.cpu())
            
            if isinstance(batch, tuple) and len(batch) > 1:
                all_labels.append(batch[1])
    
    if not all_labels:
        print("No labels available for validation")
        return 0.0
    
    all_embeddings = torch.cat(all_embeddings)
    all_labels = torch.cat(all_labels)
    
    # Calculate accuracy using 1-NN
    correct = 0
    total = 0
    
    for i in range(len(all_embeddings)):
        query_emb = all_embeddings[i]
        query_label = all_labels[i]
        
        # Calculate distances to all other embeddings
        distances = torch.sum((all_embeddings - query_emb) ** 2, dim=1)
        distances[i] = float('inf')  # Exclude self
        
        # Find nearest neighbor
        nearest_idx = distances.argmin()
        predicted_label = all_labels[nearest_idx]
        
        if predicted_label == query_label:
            correct += 1
        total += 1
    
    accuracy = correct / total
    return accuracy


def save_checkpoint(model, optimizer, epoch, accuracy, filepath):
    """Save model checkpoint"""
    
    torch.save({
        'epoch': epoch,
        'model_state_dict': model.state_dict(),
        'optimizer_state_dict': optimizer.state_dict(),
        'accuracy': accuracy,
        'backbone': BACKBONE,
        'embedding_dim': EMBEDDING_DIM,
    }, filepath)
    print(f"✅ Saved checkpoint: {filepath}")


# ==================== MAIN TRAINING ====================

def main():
    """Main training function"""
    
    print("\n" + "="*60)
    print("   FACE RECOGNITION TRAINING - TRIPLET LOSS")
    print("="*60)
    print(f"Device: {DEVICE}")
    print(f"Backbone: {BACKBONE}")
    print(f"Embedding dim: {EMBEDDING_DIM}")
    print(f"Margin: {MARGIN}")
    print(f"Batch size: {BATCH_SIZE}")
    print(f"Epochs: {NUM_EPOCHS}")
    print("="*60 + "\n")
    
    # Data transforms
    if AUGMENTATION:
        train_transform = transforms.Compose([
            transforms.Resize((IMAGE_SIZE, IMAGE_SIZE)),
            transforms.RandomHorizontalFlip(),
            transforms.RandomRotation(15),
            transforms.ColorJitter(brightness=0.2, contrast=0.2, saturation=0.2),
            transforms.ToTensor(),
            transforms.Normalize(mean=[0.485, 0.456, 0.406], 
                               std=[0.229, 0.224, 0.225])
        ])
    else:
        train_transform = transforms.Compose([
            transforms.Resize((IMAGE_SIZE, IMAGE_SIZE)),
            transforms.ToTensor(),
            transforms.Normalize(mean=[0.485, 0.456, 0.406], 
                               std=[0.229, 0.224, 0.225])
        ])
    
    # Create dataset
    print("Loading dataset...")
    train_dataset = TripletFaceDataset(
        TRAIN_DIR, 
        transform=train_transform,
        triplets_per_epoch=BATCH_SIZE * 100  # Generate enough triplets
    )
    
    # Create dataloader
    train_loader = DataLoader(
        train_dataset, 
        batch_size=BATCH_SIZE,
        shuffle=True,
        num_workers=4,
        pin_memory=True
    )
    
    print(f"Training samples: {len(train_dataset)}")
    
    # Create model
    print("\nCreating model...")
    model = EmbeddingNet(
        backbone=BACKBONE,
        embedding_dim=EMBEDDING_DIM,
        pretrained=PRETRAINED
    ).to(DEVICE)
    
    # Loss and optimizer
    criterion = TripletLoss(margin=MARGIN)
    optimizer = optim.Adam(model.parameters(), lr=LEARNING_RATE, weight_decay=WEIGHT_DECAY)
    scheduler = optim.lr_scheduler.StepLR(optimizer, step_size=30, gamma=0.1)
    
    # Create save directory
    os.makedirs(SAVE_DIR, exist_ok=True)
    
    # Training loop
    best_loss = float('inf')
    
    for epoch in range(NUM_EPOCHS):
        print(f"\nEpoch {epoch+1}/{NUM_EPOCHS}")
        print("-" * 60)
        
        # Regenerate triplets each epoch
        train_dataset.triplets = train_dataset._generate_triplets()
        
        # Train
        train_loss = train_epoch(model, train_loader, criterion, optimizer, DEVICE)
        
        # Update learning rate
        scheduler.step()
        
        print(f"Train Loss: {train_loss:.4f}")
        print(f"Learning Rate: {optimizer.param_groups[0]['lr']:.6f}")
        
        # Save best model
        if train_loss < best_loss:
            best_loss = train_loss
            save_checkpoint(
                model, optimizer, epoch, 0, 
                os.path.join(SAVE_DIR, 'best_model.pth')
            )
        
        # Save periodic checkpoint
        if (epoch + 1) % SAVE_EVERY == 0:
            save_checkpoint(
                model, optimizer, epoch, 0,
                os.path.join(SAVE_DIR, f'checkpoint_epoch_{epoch+1}.pth')
            )
    
    print("\n" + "="*60)
    print("   TRAINING COMPLETE!")
    print("="*60)
    print(f"Best loss: {best_loss:.4f}")
    print(f"Model saved to: {SAVE_DIR}/best_model.pth")
    print("="*60 + "\n")


# ==================== INFERENCE ====================

def load_trained_model(checkpoint_path, device='cuda'):
    """Load trained model for inference"""
    
    checkpoint = torch.load(checkpoint_path, map_location=device)
    
    # Create model
    model = EmbeddingNet(
        backbone=checkpoint.get('backbone', BACKBONE),
        embedding_dim=checkpoint.get('embedding_dim', EMBEDDING_DIM),
        pretrained=False
    ).to(device)
    
    # Load weights
    model.load_state_dict(checkpoint['model_state_dict'])
    model.eval()
    
    print(f"Loaded model from epoch {checkpoint['epoch']}")
    return model


def extract_embedding(model, image_path, device='cuda'):
    """Extract embedding from image"""
    
    transform = transforms.Compose([
        transforms.Resize((IMAGE_SIZE, IMAGE_SIZE)),
        transforms.ToTensor(),
        transforms.Normalize(mean=[0.485, 0.456, 0.406], 
                           std=[0.229, 0.224, 0.225])
    ])
    
    image = Image.open(image_path).convert('RGB')
    image = transform(image).unsqueeze(0).to(device)
    
    with torch.no_grad():
        embedding = model(image)
    
    return embedding.cpu().numpy()


# ==================== MAIN ====================

if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Train face recognition with triplet loss")
    parser.add_argument('--mode', type=str, default='train',
                       choices=['train', 'test'],
                       help='Mode: train or test')
    parser.add_argument('--checkpoint', type=str, default=None,
                       help='Path to checkpoint for testing')
    parser.add_argument('--image', type=str, default=None,
                       help='Path to test image')
    
    args = parser.parse_args()
    
    if args.mode == 'train':
        main()
    
    elif args.mode == 'test':
        if args.checkpoint is None or args.image is None:
            print("ERROR: Need --checkpoint and --image for testing")
        else:
            model = load_trained_model(args.checkpoint, DEVICE)
            embedding = extract_embedding(model, args.image, DEVICE)
            print(f"Embedding shape: {embedding.shape}")
            print(f"Embedding (first 10): {embedding[0][:10]}")
