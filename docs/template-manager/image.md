# Images

## The Image metadata schemas

The Metadata Editor provides two options for the documentation of images: a simple one based on the Dublin Come (DCMI option), and a more complex one based on the IPTC standard. It makes use of the Dublin Core metadata standard to document **external resources**. External resources are files or links that provide content other than the metadata stored in the DDI. This may consist of images files or any other resource available in digital format.

![image](https://user-images.githubusercontent.com/35276300/216791125-a81ff46f-46b5-4a58-84b0-75e8d8a0ed17.png)

The metadata schema we propose for documenting images contains two mutually-exclusive options: the Dublin Core, as a simple option, and the IPTC as a more complex and advanced solutions. The schema also contains a few metadata elements that will be used no matter which option is selected. The schema is structured as follows:

A few elements common to both options are provided to document the metadata (not the image itself), to provide some cataloguing parameters, and to set a unique identifier for the image being documented.

Then come the two options for documenting the image itself: the IPTC block of metadata elements, and the Dublin Core block of elements. Users will make use of one of them, not both.

The IPTC is the most detailed and complex schema. The version embedded in our schema is 2019.1 According to the IPTC website, “The IPTC Photo Metadata Standard is the most widely used standard to describe photos, because of its universal acceptance among news agencies, photographers, photo agencies, libraries, museums, and other related industries. It structures and defines metadata properties that allow users to add precise and reliable data about images.” The IPTC standard consists of two schemas: IPTC Core and IPTC Extension. They provide a comprehensive set of fields to document an image including information on time and geographic coverage, people and objects shown in the image, information on rights, and more. The schema is complex and in most cases only a small subset of fields will be used to document an image. Controlled vocabularies are recommended for some elements.

The Dublin Core (DCMI) is a simpler and highly flexible standard, composed of 15 core elements which we supplement with a few elements mostly taken from the ImageObject schema from schema.org.

Last, a small number of additional metadata elements are provided, which are common to both options described above.

Whether the IPTC or the simpler DCMI option is used, the metadata should be made as rich as possible.

## Recommendations

Recommendations

- To make the documents discoverable: caption and keywords, and possibly topics. Use ML tools like Google Vision for labeling images. For topics, use a controlled vocabulary if available.
- What you can get from EXIF. Modern digital cameras automatically generate metadata and embed it into the image file. This metadata is known as the Exchangeable Image File Format or EXIF. EXIF will record information on the date and time the image was taken, on the GPS location coordinates (latitude & longitude, possibly altitude) if the camera was equipped with a GPS and geolocation was enabled, information on the device including manufacturer and model, technical information (lens type, focal range, aperture, shutter speed, flash settings), the system-generated unique image identifier, and more. But with the exception of the date, location (if captured), and unique image identifier, the content of the EXIF does not provide information that users interested in identifying images based on their source and/or content will find useful. Metadata describing the content and source of an image will have to be obtained from another source or using other tools.

## Creating a new project

From the Project page, click on "Create new project". When prompted, select "Image".
![image](https://user-images.githubusercontent.com/35276300/216628250-5427e25d-6064-4b27-9c32-ac5edca22f50.png)

The default template is activated. The DCMI is the one recommended. If you need to change the template, select "Switch template".
[image]

We describe the documentation process with an example. We selected an image from the World Bank Flickr collection. The image is available at [https://www.flickr.com/photos/worldbank/8120361619/in/album-72157648790716931/](https://www.flickr.com/photos/worldbank/1129045136/in/album-72157608066422023/)
![image](https://user-images.githubusercontent.com/35276300/216649118-10c7030f-ff77-4782-a04e-875ceadc58bb.png)

Some metadata is provided with the photo.
![image](https://user-images.githubusercontent.com/35276300/216649278-fd13571a-7cd5-4970-b684-b0c04e4f2a1d.png)
![image](https://user-images.githubusercontent.com/35276300/216649380-2ed6da5d-0795-4c67-862c-39e852cfb8ef.png)

The image itself (low resolution) will be used as thumbnail (in a NADA catalog). 
[image]

Enter the metadata. Be as complete as you can.
![image](https://user-images.githubusercontent.com/35276300/216682630-8feb34dc-5b6c-4547-b875-6019440f6a52.png)

Example of use of Google Vision:
https://cloud.google.com/vision
![image](https://user-images.githubusercontent.com/35276300/216649741-a3ea08ed-d30d-4f53-8824-28b54a980d42.png)

![image](https://user-images.githubusercontent.com/35276300/216650120-5c5146c8-7e2d-4b12-b07e-eb25121f2095.png)

Review ! Not all accurate.
![image](https://user-images.githubusercontent.com/35276300/216650257-1ebf6baf-494d-4391-b87e-aefc03187b7d.png)


Add the image file(s) or a link to the image as an external resource. SAVE.
The image is made available in multiple formats. We assume that we want to only provide access to the small, medium and original version of the image available in our NADA catalog.
![image](https://user-images.githubusercontent.com/35276300/216649633-3b6274df-ec3e-4cf0-85a8-59f656f29222.png)

All complete. No validation error.
[image]

Saving the metadata:
JSON

The metadata can be published in a NADA catalog. 
[image]

