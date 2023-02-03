# Video

## The Video metadata schema


## Preparing your video

Recommendations

- **Embedded version**. To be embedded, a video must be hosted on a video sharing platform like Youtube (www.youtube.com). To obtain the “embed link” from youtube, click on the “Share” button, then “Embed”. In the result box, select the content of the element "src =".

![image](https://user-images.githubusercontent.com/35276300/216690631-1dbd98ff-97e0-4f25-8acf-b7417dc3c6de.png)

- **Get transcript**. Videos typically come with limited metadata. To make them more discoverable, a transcription of the video content can be generated, stored, and indexed in the catalog. The metadata schema we propose includes an element transcription that can store transcriptions (and possibly their automatically-generated translations) in the video metadata. Word embedding models and topic models can be applied to the transcriptions to further augment the metadata. This will significantly increase the discoverability of the resource, and offer the possibility to apply semantic searchability on video metadata.

Machine learning speech-to-text solutions are available (although not for all languages) to automatically generate transcriptions at a low cost. This includes commercial applications like Microsoft Azure’s , Google Cloud, Amazon Transcribe, or IBM Watson. Open source solutions in Python also exist.

Transcriptions of videos published on Youtube are available on-line (the example below was extracted from https://www.youtube.com/watch?v=Axs8NPVYmms).

![image](https://user-images.githubusercontent.com/35276300/216690359-71acc69b-8072-425e-8c18-bda307f49482.png)

Note that some care must be taken when adding automatic speech transcriptions into your metadata, as the transcriptions are not always perfect and may return unexpected results. This will be the case when the sound quality is low, or when the video includes sections in an unknown language (see the example below, of a video in English that includes a brief segmnent in Somali; the speech-to-text algorithm may in such case attempt to transcribe text it does not recognize, returning invalid information).

## Creating a new project

From the Project page, click on "Create new project". When prompted, select "Video".
![image](https://user-images.githubusercontent.com/35276300/216628250-5427e25d-6064-4b27-9c32-ac5edca22f50.png)

The default template is activated. If you need to change the template, select "Switch template".
[image]

We describe the documentation process with an example. 
Video used as example: Somalia: Guterres in Mogadishu (available on YouTube at https://www.youtube.com/watch?v=7Aif1xjstws)

![image](https://user-images.githubusercontent.com/35276300/216686978-a6f2317e-42dc-4d0f-b078-24cfdfa208f0.png)

An image is used as thumbnail (in a NADA catalog). 
[image]

Enter the metadata. Be as complete as you can.

![image](https://user-images.githubusercontent.com/35276300/216693842-a80255ff-c67a-4909-983b-f86096695d24.png)

Add external resources. The video should be embedded somewhere. Otherwise, put the file.
Can repeat the link that was provided in the metadata.

SAVE.

All complete. No validation error.
[image]

Saving the metadata:
JSON

The metadata can now be published in a NADA catalog. 

![image](https://user-images.githubusercontent.com/35276300/216690847-08d5c82d-b66c-4e2e-9aef-43cb2b9c4192.png)

