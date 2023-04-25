# Research projects and scripts

## The Script metadata schema

Documenting, cataloging, and disseminating data can increase the volume and diversity of data analysis. Similarly, documenting, cataloging, and disseminating data processing and analysis scripts can also provide great value. Technological solutions such as GitHub, Jupyter Notebooks, or Jupiter Lab facilitate the preservation and sharing of code, and enable collaborative work around data analysis. Coding style guides like the Google style guides and the Guide to Reproducible Code in Ecology and Evolution by the British Ecological Society contribute to fostering the usability, adaptability, and reproducibility of code. However, these tools and guidelines do not fully address the issue of cataloging and discoverability of data processing and analysis programs and scripts.

As a complement to collaboration tools and style guides, we propose a metadata schema to document data analysis projects and scripts. The production of structured metadata will contribute not only to discoverability but also to the reproducibility, replicability, and auditability of data analytics.

To document research projects and scripts, the Metadata Editor uses a custom schema that utilizes the Dublin Core metadata standard to document external resources. External resources are files or links that provide content other than the metadata stored in the DDI. This may include programs and scripts, PDF documents, or any other resource available in digital format.

## Preparing your scripts

Before you document and catalog your research project, it is highly recommended to:

- Use a style guide to ensure that your code is readable and user-friendly.
- Collect information on the version of the software and libraries you used to run the scripts.
- Collect information on the technological environment under which the scripts were run.
- Name your scripts in meaningful manner; ideally, the file names will include a sequential number that indicates the order in which the scripts should be run (one option is to stat the script file names with 01_*filename*, 02_*filename*, etc.
- Document all datasets used in the scripts, if they are not already properly documented.
- Collect all available information on the different phases of the research project you are documenting.
- Decide on the (preferably standard) license(s) under which your materials (data, scripts, output) will be published. Ensure full compliance with regulations, copyrights, etc.
- Collect all programs and scripts, ensuring you have the latest version. If possible, prepare and test a wrapper script that runs all scripts. the scripts can be archived into a single compressed file (zip or other).
 

## Documenting a research project

The example we use is the replication code for the World Bank Policy Research Working Paper No 9412 "Predicting Food Crises". This code is written in R and made openly accessible.

Documenting the research project does not require any specific functionality of the Metadata Editor. Try and fill out as many of the fields as possible. Include the scripts as an external resource (zip file, or independent scripts). Pay attention to describing the data used in the project, and provide a link to the dataset and to the analytical output. The analytical output can be provided as an external resource, either as files or as URLs. 

![image](https://user-images.githubusercontent.com/35276300/217010762-0d5a6e81-0d20-45ac-85bc-cbf3afeb0ad0.png)

If the project home page shows no validation error, the project can be published in a NADA catalog. 

![image](https://user-images.githubusercontent.com/35276300/217000941-4310e470-2a63-457c-ba0d-b0fd43c98602.png)


