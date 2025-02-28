from google.cloud import vision
from google.cloud.vision_v1 import types
import io
import os
os.environ["GOOGLE_APPLICATION_CREDENTIALS"] = "C:\\Users\\Administrador\\Desktop\\Tesis parte practica\\API\\reportes_infra_api\\apppracticaap-74b3eade9c0b.json"



# Cargar la imagen desde el archivo
image_path = "storage\\app\\private\\public\\reportes\\OIP1.jpeg"


# Crear un cliente para Google Vision
client = vision.ImageAnnotatorClient()

# Cargar la imagen desde el archivo
with io.open(image_path, 'rb') as image_file:
    content = image_file.read()

# Crear la solicitud de imagen para el cliente de Vision
image = types.Image(content=content)

# Detectar etiquetas y objetos en la imagen
response = client.label_detection(image=image)
labels = response.label_annotations

# Obtener las etiquetas detectadas
detected_labels = [label.description for label in labels]

# También se puede realizar una detección de objetos si es necesario
response_objects = client.object_localization(image=image)
objects = response_objects.localized_object_annotations

# Obtener los nombres de los objetos detectados
detected_objects = [object.name for object in objects]

print("Etiquetas detectadas:", detected_labels)
print("Objetos detectados:", detected_objects)
