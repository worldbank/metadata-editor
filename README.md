# Metadata Editor
<p align="center"><img src="images/metadata-editor.png" ></p>

The Metadata Editor is an open-source web-based application developed by the Office of the Chief Statistician at the World Bank. It is designed to assist data curators in documenting data of various types according to specialized metadata standards. The application currently supports the following data types and corresponding metadata standards:

- For **microdata** (from surveys, censuses, or other sources): DDI CodeBook 2.5.
- For **publications and reports**: Dublin Core, enhanced with selected elements from other standards (BibTeX, MARC21).
- For **indicators** (or time series): A custom metadata schema created by the World Bank by compiling metadata schemas from multiple organizations.
- For **geographic dataset and services**: [ISO 19139](https://www.iso.org/standard/67253.html) (ISO 19115 / ISO19110 / ISO19119).
- For **images**: IPTC and Dublin Core.
- For **videos**: A combination of Dublin Core and elements from schema.org.
- For **research projects and scripts**: A custom metadata schema created by the World Bank.

The metadata schemas supported by the Metadata Editor are detailed at https://worldbank.github.io/metadata-schemas

## Getting Started

### Server Requirements

* PHP version 7 or later
* MySQL/MariDB
* Apache, IIS or NGINX

### Installation

See [Installation guide](https://worldbank.github.io/metadata-editor-docs/tech_installation.html)


### Documentation

See [Documentation](https://worldbank.github.io/metadata-editor-docs)

## License

This project is licensed under the MIT License. Additional terms applicable to intergovernmental organizations are provided in [IGO-LICENSE-ADDENDUM](IGO-LICENSE-ADDENDUM.md).


