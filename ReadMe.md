# Elements

Elements from Datahouse AG is a highly customized Content Management System (CMS) in the form of a PHP library. It contains an inline editing as well as a publication process. Moreover, individual designs can be easily imported. Elements is designed in the form of a library to be embedded in a web application and to be database agnostic.

Keyfeatures:
* Import HTML/CSS-Designs
* Logical linking of design with content-tree
* Inline-Editing of content
* Access/Rights Management with various roles
* Publication-Processes
* Multi-Linguality

## Usage
Elements mainly consists of a PHP library that can be included via composer. 

At least version 7 of PHP is required. The docker image takes care of that, but you might need to manually adjust your IDE.

The [composer file](https://github.com/datahouse/elements/blob/main/composer.json) specifies the project properties and dependencies.

To include the Elements library of Datahouse AG, add the github repository to your project's `composer.json` in the `require` key.

```
{
    "require": {
        "datahouse/elements": "0.19.*"
    }
}
```

## Contributing
We welcome contributions from the community! If you find a bug, have a feature request, or would like to contribute code, please create an issue or submit a pull request.

Before submitting a pull request, please make sure to run the tests and ensure that your code meets the project's coding standards.

## License
Elements is released under the BSD 3-Clause license. See LICENSE for more information.

