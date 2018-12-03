<?php
/**
 * Created by PhpStorm.
 * User: souldigital
 * Date: 11/10/16
 * Time: 18:02
 */

namespace Univer\Contracts;


use Illuminate\Database\Eloquent\Relations\Relation;
use Univer\Transformers\RentableTransformer;

interface iMediaInterface
{

    /**
     * Retorna o tipo da mídia (show|season|video)
     * @return string
     */
    public function getMediaType();

    /**
     * @return int chave primaria da entidade
     */
    public function getMediaId();

    /**
     * Retorna uma instancia de transformer para o objeto atual.
     * @return RentableTransformer
     */
    public function getTransformer();

    /**
     * Retorna a chave primária
     * @return string
     */
    public function getPrimaryKey();

    /**
     * Retorna o nome composto do episódio, ou da temporada, ou por final nome da série ou filme.
     * @return mixed
     */
    public function presentName();

    /**
     * Retorna relationship com tabela title_information
     * @return Relation
     */
    public function info();
}