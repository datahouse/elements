<?php

namespace Datahouse\Elements;

use Datahouse\Elements\Presentation\Exceptions\ConfigurationError;

/**
 * Stupid constants.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class Constants
{
    // Elements version
    const VERSION_MAJOR = 0;
    const VERSION_MINOR = 19;
    const VERSION_PATCH = 12;

    // Storage version, incremented only if the storage layout changes and
    // needs migration. An increment here requires a bump of the minor
    // version above.
    const STORAGE_VERSION = 7;

    // Hard-coded element id of the root, intentionally not very random.
    const ROOT_ELEMENT_ID = '0000000000000000000000000000000000000000';

    // Max number of undo entries per file.
    const YAML_UNDO_ENTRIES_PER_FILE = 10;

    // Min number of unreachable element versions before creating a new file
    // in the attic.
    const YAML_MIN_VERSION_ENTRIES = 10;

    // The image to display if the template asks for an image but the admin
    // didn't upload one, yet.
    const MISSING_IMAGE_INDICATOR
        = '/assets/elements/images/images-pictures-photos-collection-glyph.png';

    // Set of valid element types (as set'y as PHP can get).
    const VALID_ELEMENT_TYPES = [
        'collection' => true,
        'page' => true,
        'root' => true,
        'search' => true,
        'snippet' => true,
    ];

    const ELEMENT_TYPES_WITH_SLUGS = ['page', 'search'];

    /**
     * Returns the ROOT_URL as set via environment variable. Not strictly
     * speaking a constant, but constant enough to fit in here.
     *
     * @return string external url of the website or application
     * @throws ConfigurationError
     */
    public static function getRootUrl() : string
    {
        $rootUrl = getenv('ROOT_URL');
        if (!$rootUrl) {
            throw new ConfigurationError('Fatal error: ROOT_URL is not set');
        }
        return $rootUrl;
    }
}
