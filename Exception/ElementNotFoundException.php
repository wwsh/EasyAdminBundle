<?php

/*
 * This file is part of the EasyAdminBundle.
 *
 * (c) Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JavierEguiluz\Bundle\EasyAdminBundle\Exception;

/**
 * A renamed version of EntityNotFoundException.
 * Suits better the terminology.
 * 
 * @author Thomas Parys <thomas.parys@wwsh.io>
 */
class ElementNotFoundException extends BaseException
{
    public function __construct(array $parameters = array())
    {
        $errorMessage = sprintf('The "%s" element with "%s = %s" does not exist in the database.', $parameters['element']['name'], $parameters['element']['primary_key_field_name'], $parameters['element_id']);
        $proposedSolution = sprintf('Check that the mentioned entity hasn\'t been deleted by mistake.');

        parent::__construct($errorMessage, $proposedSolution, 404);
    }
}
