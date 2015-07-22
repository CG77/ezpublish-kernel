<?php
/**
 * File containing the URLAliasRefList class
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version 2014.11.1
 */

namespace eZ\Publish\Core\REST\Server\Values;

use eZ\Publish\Core\REST\Common\Value as RestValue;

/**
 * URLAlias ref list view model
 */
class URLAliasRefList extends RestValue
{
    /**
     * URL aliases
     *
     * @var \eZ\Publish\API\Repository\Values\Content\URLAlias[]
     */
    public $urlAliases;

    /**
     * Path that was used to fetch the list of URL aliases
     *
     * @var string
     */
    public $path;

    /**
     * Construct
     *
     * @param \eZ\Publish\API\Repository\Values\Content\URLAlias[] $urlAliases
     * @param string $path
     */
    public function __construct( array $urlAliases, $path )
    {
        $this->urlAliases = $urlAliases;
        $this->path = $path;
    }
}