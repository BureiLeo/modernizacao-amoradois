<?php

namespace App\Services\Compras;

use RuntimeException;
use SimpleXMLElement;

/**
 * Leitor do XML da NF-e do fornecedor.
 *
 * O objetivo aqui nao e validar a nota fiscalmente, e so extrair o que
 * interessa para o estoque: fornecedor, numero/chave, frete, desconto e
 * a lista de itens com quantidade e valor. O resultado alimenta a tela
 * de compra em lote, onde a pessoa confere e ajusta antes de salvar.
 */
class NfeXmlParser
{
    /**
     * @return array{numero_nota: ?string, chave_nfe: ?string, data: ?string, fornecedor_nome: ?string, fornecedor_cnpj: ?string, frete: float, desconto: float, itens: array<int, array<string, mixed>>}
     */
    public function parse(string $content): array
    {
        $xml = $this->load($content);

        $infNFe = $this->first($xml, '//infNFe');

        if (! $infNFe instanceof SimpleXMLElement) {
            throw new RuntimeException('O arquivo enviado não parece ser um XML de NF-e válido.');
        }

        $itens = [];

        foreach ($infNFe->xpath('.//det') ?: [] as $det) {
            $prod = $det->prod ?? null;

            if (! $prod instanceof SimpleXMLElement) {
                continue;
            }

            $quantidade = $this->number($prod->qCom ?? null);
            $valorTotal = $this->number($prod->vProd ?? null);
            $valorUnitario = $this->number($prod->vUnCom ?? null);

            if ($valorUnitario <= 0 && $quantidade > 0) {
                $valorUnitario = $valorTotal / $quantidade;
            }

            $descricao = trim((string) ($prod->xProd ?? ''));

            if ($descricao === '' && $quantidade <= 0) {
                continue;
            }

            $itens[] = [
                'descricao' => $descricao,
                'codigo' => trim((string) ($prod->cProd ?? '')) ?: null,
                'unidade' => $this->unidade((string) ($prod->uCom ?? '')),
                'quantidade' => round($quantidade, 3),
                'valor_unitario' => round($valorUnitario, 6),
                'valor_total' => round($valorTotal, 2),
            ];
        }

        if ($itens === []) {
            throw new RuntimeException('Nenhum item foi encontrado no XML da nota.');
        }

        $totais = $this->first($infNFe, './/total/ICMSTot');

        return [
            'numero_nota' => trim((string) ($infNFe->ide->nNF ?? '')) ?: null,
            'chave_nfe' => $this->chave($infNFe),
            'data' => $this->date((string) ($infNFe->ide->dhEmi ?? $infNFe->ide->dEmi ?? '')),
            'fornecedor_nome' => trim((string) ($infNFe->emit->xNome ?? '')) ?: null,
            'fornecedor_cnpj' => trim((string) ($infNFe->emit->CNPJ ?? $infNFe->emit->CPF ?? '')) ?: null,
            'frete' => $totais ? round($this->number($totais->vFrete ?? null), 2) : 0.0,
            'desconto' => $totais ? round($this->number($totais->vDesc ?? null), 2) : 0.0,
            'itens' => $itens,
        ];
    }

    /**
     * O XML da NF-e vem com namespace padrao (e as vezes com prefixo).
     * Remover ambos deixa o XPath simples e funciona tanto para
     * <nfeProc> quanto para <NFe> avulso.
     */
    protected function load(string $content): SimpleXMLElement
    {
        $content = trim($content);

        if ($content === '') {
            throw new RuntimeException('O arquivo XML está vazio.');
        }

        $content = preg_replace('/\sxmlns(:\w+)?="[^"]*"/i', '', $content) ?? $content;
        $content = preg_replace('/(<\/?)\w+:/', '$1', $content) ?? $content;

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml === false) {
            throw new RuntimeException('Não foi possível ler o XML enviado.');
        }

        return $xml;
    }

    protected function first(SimpleXMLElement $node, string $xpath): ?SimpleXMLElement
    {
        $found = $node->xpath($xpath);

        return $found ? $found[0] : null;
    }

    protected function chave(SimpleXMLElement $infNFe): ?string
    {
        $id = (string) ($infNFe->attributes()->Id ?? '');
        $chave = preg_replace('/\D/', '', $id) ?? '';

        return strlen($chave) === 44 ? $chave : null;
    }

    protected function date(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    protected function number(mixed $value): float
    {
        $raw = trim((string) $value);

        return $raw === '' ? 0.0 : (float) $raw;
    }

    protected function unidade(string $value): string
    {
        $value = strtolower(trim($value));

        return $value !== '' ? substr($value, 0, 16) : 'un';
    }
}
