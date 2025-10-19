#!/usr/bin/env python3
import sys
import json
import torch
import torch.nn as nn
from torchvision import models, transforms
from PIL import Image
import numpy as np
import os

sys.path.append(os.path.dirname(os.path.abspath(__file__)))

class PlantDiseaseResNet(nn.Module):
    def __init__(self, num_classes, num_plants):
        super(PlantDiseaseResNet, self).__init__()
        self.backbone = models.resnet50(weights=None)
        in_features = self.backbone.fc.in_features
        self.backbone.fc = nn.Identity()
        
        self.disease_classifier = nn.Sequential(
            nn.Dropout(0.5),
            nn.Linear(in_features, 1024),
            nn.ReLU(inplace=True),
            nn.BatchNorm1d(1024),
            nn.Dropout(0.3),
            nn.Linear(1024, 512),
            nn.ReLU(inplace=True),
            nn.BatchNorm1d(512),
            nn.Dropout(0.2),
            nn.Linear(512, num_classes)
        )
        
        self.plant_classifier = nn.Sequential(
            nn.Dropout(0.3),
            nn.Linear(in_features, 256),
            nn.ReLU(inplace=True),
            nn.BatchNorm1d(256),
            nn.Linear(256, num_plants)
        )
        
        self.severity_head = nn.Sequential(
            nn.Dropout(0.3),
            nn.Linear(in_features, 128),
            nn.ReLU(inplace=True),
            nn.BatchNorm1d(128),
            nn.Linear(128, 3)
        )
    
    def forward(self, x):
        features = self.backbone(x)
        disease_output = self.disease_classifier(features)
        plant_output = self.plant_classifier(features)
        severity_output = self.severity_head(features)
        return disease_output, plant_output, severity_output

class PlantDiseaseDetector:
    def __init__(self, model_path):
        self.device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')
        print(f"Using device: {self.device}", file=sys.stderr)
        
        if not os.path.exists(model_path):
            raise Exception(f"Model file not found at: {model_path}")
        
        try:
            checkpoint = torch.load(model_path, map_location=self.device, weights_only=True)
        except:
            try:
                torch.serialization.add_safe_globals([transforms.Compose])
                checkpoint = torch.load(model_path, map_location=self.device, weights_only=True)
            except:
                try:
                    print("Warning: Using weights_only=False. Only use this if you trust the model file.", file=sys.stderr)
                    checkpoint = torch.load(model_path, map_location=self.device, weights_only=False)
                except Exception as e:
                    raise Exception(f"Failed to load model: {str(e)}")
        
        # Extract model parameters
        try:
            self.class_names = checkpoint['class_names']
            self.plant_types = checkpoint['plant_types']
            self.idx_to_plant = checkpoint['idx_to_plant']
            
            num_classes = len(self.class_names)
            num_plants = len(self.plant_types)
            
            print(f"Number of classes: {num_classes}", file=sys.stderr)
            print(f"Number of plants: {num_plants}", file=sys.stderr)
            print(f"idx_to_plant type: {type(self.idx_to_plant)}", file=sys.stderr)
            print(f"idx_to_plant contents: {self.idx_to_plant}", file=sys.stderr)
            
        except KeyError as e:
            raise Exception(f"Missing required key in checkpoint: {e}")
            
        # Initialize model
        self.model = PlantDiseaseResNet(num_classes=num_classes, num_plants=num_plants)
        
        try:
            self.model.load_state_dict(checkpoint['model_state_dict'])
        except Exception as e:
            print(f"Error loading state dict: {e}", file=sys.stderr)
            # Try to handle size mismatches by loading compatible parts
            model_dict = self.model.state_dict()
            pretrained_dict = {k: v for k, v in checkpoint['model_state_dict'].items() 
                             if k in model_dict and model_dict[k].shape == v.shape}
            model_dict.update(pretrained_dict)
            self.model.load_state_dict(model_dict, strict=False)
            print(f"Loaded {len(pretrained_dict)}/{len(model_dict)} parameters", file=sys.stderr)
        
        self.model.to(self.device)
        self.model.eval()
        
        self.transform = transforms.Compose([
            transforms.Resize((224, 224)),
            transforms.ToTensor(),
            transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225])
        ])
        
        print(f"Model loaded successfully. Classes: {num_classes}, Plants: {num_plants}", file=sys.stderr)
    
    def _get_plant_name(self, plant_idx):
        """Safely get plant name from index"""
        try:
            plant_key = str(plant_idx)
            if plant_key in self.idx_to_plant:
                return self.idx_to_plant[plant_key]
            if plant_idx in self.idx_to_plant:
                return self.idx_to_plant[plant_idx]
            if plant_idx < len(self.plant_types):
                return self.plant_types[plant_idx]
            
            return "Unknown"
            
        except Exception as e:
            print(f"Error getting plant name for index {plant_idx}: {e}", file=sys.stderr)
            return "Unknown"
    
    def predict(self, image_path):
        """Make prediction on a single image"""
        try:
            # Load and preprocess image
            if not os.path.exists(image_path):
                raise Exception(f"Image file not found: {image_path}")
                
            image = Image.open(image_path).convert('RGB')
            input_tensor = self.transform(image).unsqueeze(0).to(self.device)
            
            with torch.no_grad():
                disease_outputs, plant_outputs, severity_outputs = self.model(input_tensor)
                
                # Get probabilities
                disease_probs = torch.softmax(disease_outputs, dim=1)
                plant_probs = torch.softmax(plant_outputs, dim=1)
                severity_probs = torch.softmax(severity_outputs, dim=1)
                
                # Get predictions
                disease_prob, disease_pred = torch.max(disease_probs, 1)
                plant_prob, plant_pred = torch.max(plant_probs, 1)
                severity_prob, severity_pred = torch.max(severity_probs, 1)
            
            # Convert to Python types
            disease_idx = disease_pred.item()
            plant_idx = plant_pred.item()
            severity_idx = severity_pred.item()
            
            print(f"Raw indices - disease: {disease_idx}, plant: {plant_idx}, severity: {severity_idx}", file=sys.stderr)
            
            # Validate indices
            if disease_idx >= len(self.class_names):
                raise Exception(f"Disease index {disease_idx} out of range (0-{len(self.class_names)-1})")
            if plant_idx >= len(self.plant_types):
                raise Exception(f"Plant index {plant_idx} out of range (0-{len(self.plant_types)-1})")
            if severity_idx >= 3:
                raise Exception(f"Severity index {severity_idx} out of range (0-2)")
            
            # Get predictions
            predicted_disease = self.class_names[disease_idx]
            predicted_plant = self._get_plant_name(plant_idx)
            disease_confidence = disease_probs[0, disease_idx].item()
            plant_confidence = plant_probs[0, plant_idx].item()
            severity_level = ['Low', 'Medium', 'High'][severity_idx]
            severity_confidence = severity_prob.item()
            
            disease_plant = predicted_disease.split('_')[0]
            if disease_plant != predicted_plant:
                print(f"Warning: Plant-disease mismatch. Disease suggests: {disease_plant}, Model predicted: {predicted_plant}", file=sys.stderr)
                # Use the plant from the disease name as it's more reliable
                predicted_plant = disease_plant
            
            is_healthy = 'healthy' in predicted_disease.lower()
            
            result = {
                'plant_type': predicted_plant,
                'plant_confidence': float(plant_confidence),
                'disease_name': predicted_disease,
                'disease_confidence': float(disease_confidence),
                'severity': severity_level,
                'severity_confidence': float(severity_confidence),
                'is_healthy': is_healthy
            }
            
            print(f"Final result: {result}", file=sys.stderr)
            return result
            
        except Exception as e:
            print(f"Prediction error: {str(e)}", file=sys.stderr)
            import traceback
            print(f"Traceback: {traceback.format_exc()}", file=sys.stderr)
            raise Exception(f"Prediction failed: {str(e)}")

def main():
    if len(sys.argv) != 2:
        result = {'success': False, 'error': 'Usage: python disease_detector.py <image_path>'}
        print(json.dumps(result))
        sys.exit(1)
    
    image_path = sys.argv[1]
    
    try:
        model_path = os.path.join(os.path.dirname(__file__), 'models', 'plant_disease_model_complete.pth')
        print(f"Looking for model at: {model_path}", file=sys.stderr)
        
        detector = PlantDiseaseDetector(model_path)
        result = detector.predict(image_path)
        result['success'] = True
        
        print(json.dumps(result))
        
    except Exception as e:
        error_result = {'success': False, 'error': str(e)}
        print(json.dumps(error_result))
        sys.exit(1)

if __name__ == '__main__':
    main()