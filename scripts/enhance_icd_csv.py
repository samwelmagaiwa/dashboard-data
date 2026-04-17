import csv
import re

def generate_abbreviation(code, description):
    desc = description.lower()
    
    # 1. Primary Mappings (High Priority)
    if "non-st-elevation myocardial infarction" in desc or ("subendocardial" in desc and "myocardial infarction" in desc):
        return "NSTEMI"
    
    if "st-elevation myocardial infarction" in desc or ("transmural" in desc and "myocardial infarction" in desc):
        location = ""
        if "anterior" in desc: location = "Anterior "
        elif "inferior" in desc: location = "Inferior "
        elif "posterior" in desc: location = "Posterior "
        elif "lateral" in desc: location = "Lateral "
        elif "septal" in desc: location = "Septal "
        
        return f"{location}STEMI".strip()
    
    if "acute myocardial infarction" in desc:
        return "AMI"
    
    if "myocardial infarction" in desc:
        return "MI"
    
    if "chronic kidney disease" in desc:
        result = "CKD"
        if "stage 1" in desc: result += " Stage 1"
        elif "stage 2" in desc: result += " Stage 2"
        elif "stage 3" in desc: result += " Stage 3"
        elif "stage 4" in desc: result += " Stage 4"
        elif "stage 5" in desc: result += " Stage 5"
        elif "end-stage" in desc: result += " ESRD"
        
        if "unspecified" in desc: result += " NOS"
        return result.strip()
    
    if "diabetes mellitus" in desc:
        result = "DM"
        if "type 1" in desc: result = "T1DM"
        elif "type 2" in desc: result = "T2DM"
        if "unspecified" in desc: result += " NOS"
        return result
    
    if "hypertension" in desc:
        return "HTN"
    
    if "tuberculosis" in desc:
        if "respiratory" in desc or "pulmonary" in desc: return "PTB"
        return "TB"
    
    if "human immunodeficiency virus" in desc or "hiv disease" in desc:
        return "HIV"
    
    if "heart failure" in desc:
        if "chronic" in desc: return "CHF"
        if "congestive" in desc: return "CHF"
        return "HF"
    
    if "cerebrovascular accident" in desc or "stroke" in desc:
        return "CVA"
    
    if "chronic obstructive pulmonary disease" in desc:
        return "COPD"
    
    if "urinary tract infection" in desc:
        return "UTI"
    
    # 2. NOS Rule (Unspecified / Not Otherwise Specified)
    is_unspecified = "unspecified" in desc or "not otherwise specified" in desc or "nos" in desc
    
    # 3. Fallback logic for generating short forms
    # Remove common filler words
    clean_desc = re.sub(r'\(.*?\)', '', description) # Remove parentheses
    clean_desc = re.sub(r'\[.*?\]', '', clean_desc) # Remove brackets
    clean_desc = clean_desc.replace(',', '').replace(';', '').replace(':', '')
    words = [w for w in clean_desc.split() if w.lower() not in ['of', 'and', 'the', 'due', 'to', 'with', 'other', 'specified', 'unspecified']]
    
    if not words:
        return "NOS"
        
    # Standard acronym generation for remaining cases
    if len(words) >= 2:
        # Take first 2-3 words or their initials
        acronym = "".join([w[0].upper() for w in words[:3]])
        # Special case: if it's very short, just use the word
        if len(words[0]) <= 4 and len(words) == 1:
            acronym = words[0].upper()
    else:
        acronym = words[0][:4].upper()
        
    if is_unspecified and "NOS" not in acronym:
        return f"{acronym} NOS"
        
    return acronym

def process_csv(input_path, output_path):
    with open(input_path, mode='r', encoding='utf-8') as infile:
        reader = csv.DictReader(infile)
        fieldnames = ['Code', 'Description', 'Abbreviation']
        
        with open(output_path, mode='w', encoding='utf-8', newline='') as outfile:
            writer = csv.DictWriter(outfile, fieldnames=fieldnames)
            writer.writeheader()
            
            for row in reader:
                code = row['Code']
                description = row['Description']
                abbreviation = generate_abbreviation(code, description)
                
                writer.writerow({
                    'Code': code,
                    'Description': description,
                    'Abbreviation': abbreviation
                })

if __name__ == "__main__":
    process_csv('icd10_codes.csv', 'icd10_codes_enhanced.csv')
    print("Enhanced CSV created: icd10_codes_enhanced.csv")
