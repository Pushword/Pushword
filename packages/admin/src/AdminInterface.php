<?php

namespace Pushword\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @template T of object
 */
interface AdminInterface
{
    /**
     * @return class-string<T>
     */
    public function getModelClass(): string;

    /**
     * @return T
     */
    public function getSubject(): object;

    /**
     * @param T $subject
     */
    public function setSubject(object $subject): void;

    public function getEntityManager(): EntityManagerInterface;

    public function hasRequest(): bool;

    public function getRequest(): ?Request;

    public function getTranslator(): TranslatorInterface;
}
