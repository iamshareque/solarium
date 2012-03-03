<?php
/**
 * Copyright 2011 Bas de Nooijer. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this listof conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDER AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * The views and conclusions contained in the software and documentation are
 * those of the authors and should not be interpreted as representing official
 * policies, either expressed or implied, of the copyright holder.
 *
 * @copyright Copyright 2011 Bas de Nooijer <solarium@raspberry.nl>
 * @license http://github.com/basdenooijer/solarium/raw/master/COPYING
 * @link http://www.solarium-project.org/
 *
 * @package Solarium
 * @subpackage QueryType
 */

/**
 * @namespace
 */
namespace Solarium\Query\Update;
use Solarium\Core\Exception;
use Solarium\Client;
use Solarium\Core\Client\Request;
use Solarium\Query\Update\Query\Query as UpdateQuery;
use Solarium\Core\Query\RequestBuilder as BaseRequestBuilder;

/**
 * Build an update request
 *
 * @package Solarium
 * @subpackage QueryType
 */
class RequestBuilder extends BaseRequestBuilder
{

    /**
     * Build request for an update query
     *
     * @param UpdateQuery $query
     * @return Request
     */
    public function build($query)
    {
        $request = parent::build($query);
        $request->setMethod(Request::METHOD_POST);
        $request->setRawData($this->getRawData($query));

        return $request;
    }

    /**
     * Generates raw POST data
     *
     * Each commandtype is delegated to a separate builder method.
     *
     * @param UpdateQuery $query
     * @throws Exception
     * @return string
     */
    public function getRawData($query)
    {
        $xml = '<update>';
        foreach ($query->getCommands() AS $command) {
            switch ($command->getType()) {
                case UpdateQuery::COMMAND_ADD:
                    $xml .= $this->buildAddXml($command);
                    break;
                case UpdateQuery::COMMAND_DELETE:
                    $xml .= $this->buildDeleteXml($command);
                    break;
                case UpdateQuery::COMMAND_OPTIMIZE:
                    $xml .= $this->buildOptimizeXml($command);
                    break;
                case UpdateQuery::COMMAND_COMMIT:
                    $xml .= $this->buildCommitXml($command);
                    break;
                case UpdateQuery::COMMAND_ROLLBACK:
                    $xml .= $this->buildRollbackXml();
                    break;
                default:
                    throw new Exception('Unsupported command type');
                    break;
            }
        }
        $xml .= '</update>';

        return $xml;
    }

    /**
     * Build XML for an add command
     *
     * @param Query\Command\Add $command
     * @return string
     */
    public function buildAddXml($command)
    {
        $xml = '<add';
        $xml .= $this->boolAttrib('overwrite', $command->getOverwrite());
        $xml .= $this->attrib('commitWithin', $command->getCommitWithin());
        $xml .= '>';

        foreach ($command->getDocuments() AS $doc) {
            $xml .= '<doc';
            $xml .= $this->attrib('boost', $doc->getBoost());
            $xml .= '>';

            foreach ($doc->getFields() AS $name => $value) {
                $boost = $doc->getFieldBoost($name);
                if (is_array($value)) {
                    foreach ($value AS $multival) {
                        $xml .= $this->buildFieldXml($name, $boost, $multival);
                    }
                } else {
                    $xml .= $this->buildFieldXml($name, $boost, $value);
                }
            }

            $xml .= '</doc>';
        }

        $xml .= '</add>';

        return $xml;
    }

    /**
     * Build XML for a field
     *
     * Used in the add command
     *
     * @param string $name
     * @param float $boost
     * @param mixed $value
     * @return string
     */
    protected function buildFieldXml($name, $boost, $value)
    {
        $xml = '<field name="' . $name . '"';
        $xml .= $this->attrib('boost', $boost);
        $xml .= '>' . htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8');
        $xml .= '</field>';

        return $xml;
    }

    /**
     * Build XML for a delete command
     *
     * @param Query\Command\Delete $command
     * @return string
     */
    public function buildDeleteXml($command)
    {
        $xml = '<delete>';
        foreach ($command->getIds() AS $id) {
            $xml .= '<id>' . htmlspecialchars($id, ENT_NOQUOTES, 'UTF-8')
                    . '</id>';
        }
        foreach ($command->getQueries() AS $query) {
            $xml .= '<query>' . htmlspecialchars($query, ENT_NOQUOTES, 'UTF-8')
                    . '</query>';
        }
        $xml .= '</delete>';

        return $xml;
    }

    /**
     * Build XML for an update command
     *
     * @param Query\Command\Optimize $command
     * @return string
     */
    public function buildOptimizeXml($command)
    {
        $xml = '<optimize';
        $xml .= $this->boolAttrib('waitFlush', $command->getWaitFlush());
        $xml .= $this->boolAttrib('waitSearcher', $command->getWaitSearcher());
        $xml .= $this->attrib('maxSegments', $command->getMaxSegments());
        $xml .= '/>';

        return $xml;
    }

    /**
     * Build XML for a commit command
     *
     * @param Query\Command\Commit $command
     * @return string
     */
    public function buildCommitXml($command)
    {
        $xml = '<commit';
        $xml .= $this->boolAttrib('waitFlush', $command->getWaitFlush());
        $xml .= $this->boolAttrib('waitSearcher', $command->getWaitSearcher());
        $xml .= $this->boolAttrib(
            'expungeDeletes',
            $command->getExpungeDeletes()
        );
        $xml .= '/>';

        return $xml;
    }

    /**
     * Build XMl for a rollback command
     *
     * @return string
     */
    public function buildRollbackXml()
    {
        return '<rollback/>';
    }

}