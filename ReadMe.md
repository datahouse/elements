# Elements

Elements from Datahouse AG is a highly customized Content Management System (CMS) in the form of a PHP library and an administration frontend. It contains an inline editing as well as a publication process. Moreover, individual designs can be easily imported.

Keyfeatures:
* Import HTML/CSS-Designs
* Logical linking of design with content-tree
* Inline-Editing of content
* Access/Rights-Management with various roles
* Publication-Processes
* Multi-Linguality

## Installation & Usage
Elements selbst ist eine Library und bietet nicht selbst einen Webservice an. Siehe stattdessen die folgenden Beispielprojekte, die Elements nutzen: WWP und WWW (branch elementarization).

Während der Entwicklung von Elements selbst macht es mehr Sinn, diese Library als Symlink einzubinden. Sowohl für WWP wie auch WWW ist das momentan so implementiert. Aufsetzen und starten der entsprechenden Container ist wie folgt möglich:

### build elements, assuming it's already checked out at $(ELE_CHECKOUT)
cd $(ELE_CHECKOUT)
ant setup build

### build WWP, assuming it's already checked out at $(WWP_CHECKOUT)
cd $(WWP_CHECKOUT)

### symlink to the elements checkout
ln -s $(ELE_CHECKOUT) elements

### now ant has all files necessary
ant setup build
cd $(WWP_CHECKOUT)/dc/wwp-dev

### start the docker -dev container, properly linked to the elements source tree
ELE_CHECKOUT=$(ELE_CHECKOUT) ROOT_URL="http://localhost:8001" docker-compose up -d

### rinse and repeat for WWW 
cd $(WWW_CHECKOUT)
ln -s $(ELE_CHECKOUT) elements
ant setup build
cd $(WWW_CHECKOUT)/dc/web-dev
ELE_CHECKOUT=$(ELE_CHECKOUT) ROOT_URL="http://localhost:8002" docker-compose up -d


## Contributing
We welcome contributions from the community! If you find a bug, have a feature request, or would like to contribute code, please create an issue or submit a pull request.

Before submitting a pull request, please make sure to run the tests and ensure that your code meets the project's coding standards.

## License
Elements is released under the BSD 3-Clause license. See LICENSE for more information.

