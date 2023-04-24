# Images

![image](https://user-images.githubusercontent.com/35276300/216791125-a81ff46f-46b5-4a58-84b0-75e8d8a0ed17.png)

## The Image metadata schemas

The Metadata Editor provides two mutually-exclusive options for documenting images: a simple one based on the Dublin Core (DCMI option), and a more complex one based on the IPTC standard. The schema also contains a few metadata elements that will be used regardless of which option is selected. 

The schema is structured as follows:
- First, a few elements common to both options are provided to document the metadata (not the image itself), to provide some cataloging parameters, and to set a unique identifier for the image being documented.
- Next, there are two options for documenting the image itself: the IPTC block of metadata elements and the Dublin Core block of elements. You will make use of one of them, not both. The IPTC is the most detailed and complex schema. The version embedded in our schema is 2019.1. According to the IPTC website, "The IPTC Photo Metadata Standard is the most widely used standard to describe photos because of its universal acceptance among news agencies, photographers, photo agencies, libraries, museums, and other related industries. It structures and defines metadata properties that allow users to add precise and reliable data about images." The IPTC standard consists of two schemas: IPTC Core and IPTC Extension. They provide a comprehensive set of fields to document an image, including information on time and geographic coverage, people and objects shown in the image, information on rights, and more. The schema is complex, and in most cases, only a small subset of fields will be used to document an image. Controlled vocabularies are recommended for some elements. The Dublin Core (DCMI) is a simpler and highly flexible standard, composed of 15 core elements, which we supplement with a few elements mostly taken from the ImageObject schema from schema.org.
- Finally, a small number of additional metadata elements are provided, which are common to both options described above.

Whether the IPTC or the simpler DCMI option is used, the metadata should be made as comprehensive as possible.


## Recommendations

To make the documents discoverable, include a caption, keywords, and possibly topics. Utilize machine learning tools such as Google Vision to label images. When possible, use a controlled vocabulary for topics.

Extract information from the EXIF metadata if available. Modern digital cameras automatically generate metadata and embed it into the image file. This metadata is known as Exchangeable Image File Format (EXIF). EXIF records information on the date and time the image was taken, GPS location coordinates (latitude & longitude, possibly altitude) if the camera was equipped with GPS and geolocation was enabled, information on the device including manufacturer and model, technical information (lens type, focal range, aperture, shutter speed, flash settings), system-generated unique image identifier, and more. From this information, the following elements are useful and should be extracted when available: date, location (if captured), and unique image identifier.


## Documenting an image

To document a new image, click on "Create new project" in the Project page and select "Image" when prompted. A new project page will open with the default template which you can change if needed by clicking "Switch template". To edit an image, find and click on the image title in the list in the Projects page.

![image](https://user-images.githubusercontent.com/35276300/216628250-5427e25d-6064-4b27-9c32-ac5edca22f50.png)

An image usually has limited metadata, such as the date it was generated or the name of the author/photographer, which is useful but not very relevant when indexed in a catalog with semantic search capability. Ideally, the metadata should include a description of the content and location of the image. A title or caption provided by the image producer or curator is highly valuable, but additional information can be generated to enhance the metadata. If the GPS location of a photo is known, it can be used to extract information on the geographic area where the photo was taken (country/province/town/locality). Machine learning tools can also be used to automatically generate labels describing the contents of the image, which can be stored as keywords in the image metadata. While these solutions are powerful, they are not perfect, and should be used as a supplement to human curation, rather than a replacement. 

We describe this process with an example. We selected an image from the World Bank Flickr collection. The image is available at [https://www.flickr.com/photos/worldbank/8120361619/in/album-72157648790716931/](https://www.flickr.com/photos/worldbank/1129045136/in/album-72157608066422023/)

![image](https://user-images.githubusercontent.com/35276300/216649118-10c7030f-ff77-4782-a04e-875ceadc58bb.png)

Some metadata is provided with the photo, including tags (which may be captured as "keywords", a brief description, and information on the geographic location. All this information should be entered in the appropriate metadata fields available in the selected template.

![image](https://user-images.githubusercontent.com/35276300/216649278-fd13571a-7cd5-4970-b684-b0c04e4f2a1d.png)

![image](https://user-images.githubusercontent.com/35276300/216649380-2ed6da5d-0795-4c67-862c-39e852cfb8ef.png)

![image](https://user-images.githubusercontent.com/35276300/216682630-8feb34dc-5b6c-4547-b875-6019440f6a52.png)

You may use a low resolution JPG copy of the image as thumbnail. 

To suggest labels to be used as keywords to describe the image, you may also use an AI application like Google Vision:
https://cloud.google.com/vision

![image](https://user-images.githubusercontent.com/35276300/216649741-a3ea08ed-d30d-4f53-8824-28b54a980d42.png)

![image](https://user-images.githubusercontent.com/35276300/216650120-5c5146c8-7e2d-4b12-b07e-eb25121f2095.png)

The labels that will be returned will not always be accurate. The results for the image we use as an example are mostly irrelevant; in many cases however, the AI tool will provide useful suggestions. But you will always have to inspect them and select the ones you consider relevant. And you are of course not limited to labels suggested by an AI application. You may enter your own.

![image](https://user-images.githubusercontent.com/35276300/216650257-1ebf6baf-494d-4391-b87e-aefc03187b7d.png)

Finally, add the image file(s) or a link to the file(s) as an external resource. The World Bank image we use as example is available in multiple resolutions. We assume that we want to only provide access to the small, medium and original version of the image available in our NADA catalog.

![image](https://user-images.githubusercontent.com/35276300/216649633-3b6274df-ec3e-4cf0-85a8-59f656f29222.png)

With this, the documentation process is complete. If there is no validation error in the Project page, the image is ready to be published in the NADA catalog. In NADA, images will appear in a specific tab "Images.

![image](https://user-images.githubusercontent.com/35276300/234132904-4a5983ff-29fd-4247-b24c-a8b76eeb6994.png)




