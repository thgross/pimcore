<?php

declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Document;


use Pimcore\Document\Renderer\DocumentRenderer;
use Pimcore\Logger;
use Pimcore\Tool\Storage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class StaticPageGenerator
{
    /**
     * @var DocumentRenderer
     */
    protected $documentRenderer;

    /**
     * @var LockInterface
     */
    private $lockFactory;


    public function __construct(DocumentRenderer $documentRenderer, LockFactory $lockFactory)
    {
        $this->documentRenderer = $documentRenderer;
        $this->lockFactory = $lockFactory;
    }

    /**
     * @param $document
     *
     * @return string
     */
    public function getStoragePath($document)
    {
        return $document->getRealFullPath() . '.html';
    }

    /**
     * @param $document
     *
     * @return bool
     */
    public function generate($document)
    {
        $storagePath = $this->getStoragePath($document);

        $storage = Storage::get('document_static');
        $startTime = microtime(true);

        $lockKey = 'document_static_' . $document->getId() . '_' . md5($storagePath);
        $lock = $this->lockFactory->createLock($lockKey);

        $lock->acquire(true);

        try {
            $renderedDocumentData = $this->documentRenderer->render($document);
            $storage->write($storagePath, $renderedDocumentData);
        } catch (\Exception $e) {
            Logger::debug('Error generating static Page ' . $storagePath .': ' . $e->getMessage());
            return false;
        }

        Logger::debug('Static Page ' . $storagePath . ' generated in ' . (microtime(true) - $startTime) . ' seconds');

        $lock->release();

        return true;
    }

    /**
     * @param $document
     *
     * @throws \League\Flysystem\FilesystemException
     */
    public function remove($document)
    {
        $storagePath = $this->getStoragePath($document);
        $storage = Storage::get('document_static');

        $storage->delete($storagePath);
    }
}
