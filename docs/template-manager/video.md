# Video

## The Video metadata schema

The schema proposed for documenting video files is a combination of elements extracted from the Dublin Core Metadata Initiative (DCMI) and the VideoObject schema (from schema.org), which is very similar to the schema proposed for audio files in chapter 10.

The Dublin Core is a versatile and generic standard that is also used (in an augmented form) for documenting publications and images. The schema contains 15 core elements, supplemented with a selection of elements from VideoObject, as well as keywords, topics, tags, provenance, and additional elements found in other schemas documented in the Guide.

The resulting metadata schema is simple but includes the necessary elements to document resources and their content, which will improve their discoverability in data catalogs. Compliance with VideoObject elements also contributes to search engine optimization, as search engines like Google, Bing, and others "reward" metadata published in formats compatible with the schema.org recommendations.


## Preparing your video

Before you document a video recording, we suggest you take some time preparing the materials. This includes getting a link to an embedded version, and obtaining a transcript of the text.

- **Embedded version**. To be embedded in a NADA catalog, a video must be hosted on a video sharing platform like Youtube (www.youtube.com). To obtain the “embed link” from youtube, click on the “Share” button, then “Embed”. In the result box, select the content of the element "src =".

![image](https://user-images.githubusercontent.com/35276300/216690631-1dbd98ff-97e0-4f25-8acf-b7417dc3c6de.png)

- **Get transcript**. Videos usually have limited metadata. To enhance their discoverability, a transcription of the video content can be generated, stored, and indexed in the catalog. Our proposed metadata schema includes a transcription element that can store transcriptions (and possibly their automatically-generated translations) in the video metadata. Word embedding models and topic models can be applied to the transcriptions to further enhance the metadata. This will significantly increase the discoverability of the resource and offer the possibility of applying semantic searchability on video metadata.

Machine learning speech-to-text solutions are available (although not for all languages) to automatically generate transcriptions at a low cost. This includes commercial applications like openAI's Whisper, Microsoft Azure, Google Cloud, or Amazon Transcribe. Open source solutions in Python also exist.

Transcriptions of videos published on YouTube are available online (the example below was extracted from https://www.youtube.com/watch?v=Axs8NPVYmms).

![image](https://user-images.githubusercontent.com/35276300/216690359-71acc69b-8072-425e-8c18-bda307f49482.png)

Note that some care must be taken when adding automatic speech transcriptions to your metadata, as the transcriptions are not always perfect and may yield unexpected results. This is especially true when the sound quality is poor, or when the video contains segments in an unfamiliar language (as in the example we present below, where the video is in English but includes a brief segment in Somali; the speech-to-text algorithm may try to transcribe unrecognized text, resulting in inaccurate information).


## Documenting the video

Once you have all materials ready, you can document your video by creating a new project of type "Video". 

We describe the documentation process with an example of a video available on youtube: "Somalia: Guterres in Mogadishu" (available at https://www.youtube.com/watch?v=7Aif1xjstws)

![video_somalia](https://user-images.githubusercontent.com/35276300/216694636-c629b6f1-e775-44cc-a2ad-bdf9386ce317.JPG)

A screenshot of a frame of the video can be used as thumbnail.  

Then enter as much metadata as possible, including the edited transcript.

![image](https://user-images.githubusercontent.com/35276300/216693842-a80255ff-c67a-4909-983b-f86096695d24.png)

Add a link to the video as an external resources. If your project page shows no validation error, you are ready to publish the video metadata in your catalog. In NADA, videos will appear in a specific tab "Video". If you have provided an embedded link in the metadata, the NADA catalog will provide the possibility to view the video in the NADA page itself. 

![image](https://user-images.githubusercontent.com/35276300/234133046-43ad87d0-2c66-4f51-b3f5-80e7ac78822c.png)
