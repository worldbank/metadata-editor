# The Metadata Editor API

## Description of API

Type of API
Application: API first
Where to find description (ReDoc)
What you can do with the API 

## Use cases

### Use case 1: Loading metadata from JSON

You can do this in ME by importing.
If you want to do it on a batch use R or Python and the API.

```R
# Loading a JSON file
```

### Use case 2: Loading metadata from NADA

Import metadata from NADA, and add to your Metadata Editor

```R
```

### Use case 3: Generating and loading metadata programatically 

Create metadata in R or Python, and push it to the ME.
Note: Only do that if you need to use the ME to review or edit; otherwise, can publish directly in NADA.

```R

# Provide credential
my_api_key = "abc123"

# Generate URL for Metadata Editor (with type of data)
# Type must be one of the following: microdata | geospatial | table | document | script 
# video | image | database | timeseries

me_url = "https://dev.ihsn.org/editor/index.php/api/editor/"
type   = "geospatial"
url    = paste0(url, "create/", type)

# Generate metadata

# Push to the Metadata Editor
result<-POST(url,add_headers("X-API-KEY"=my_key), body=my_geo_data, content_type_json(), encode="json", accept_json())
  
```

### Export batch


### Generate report on ME content








