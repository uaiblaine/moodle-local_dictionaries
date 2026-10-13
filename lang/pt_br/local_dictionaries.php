<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component local_dictionaries, language 'pt_br'.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['actions'] = 'Ações';
$string['cachedef_domains'] = 'Domínios de dicionários de referência lidos durante uma requisição';
$string['cachedef_labels'] = 'Rótulos resolvidos dos códigos dos dicionários de referência';
$string['createdomain'] = 'Criar ou atualizar domínio';
$string['csvfile'] = 'Arquivo CSV';
$string['csvhelp'] = 'Colunas: code, label. Uma primeira linha que nomeia as colunas code e label é um cabeçalho e pode acrescentar as colunas parentcode, sortorder e active (0 ou 1), em qualquer ordem. O delimitador (vírgula, ponto e vírgula ou tabulação) é detectado na primeira linha. Os códigos não diferenciam maiúsculas de minúsculas nem acentos; um código repetido no arquivo fica com a última linha.';
$string['csvrejectcodetoolong'] = 'Linha {$a}: o código tem mais de 255 caracteres.';
$string['csvrejectedhidden'] = 'Mais {$a} linhas rejeitadas não estão listadas.';
$string['csvrejectemptycode'] = 'Linha {$a}: o código está vazio.';
$string['csvrejectinvalidactive'] = 'Linha {$a}: active deve ser 0 ou 1.';
$string['csvrejectinvalidsortorder'] = 'Linha {$a}: sortorder deve ser um número inteiro.';
$string['csvrejectinvalidutf8'] = 'Linha {$a}: a linha não é texto UTF-8 válido.';
$string['csvrejecttoofewcolumns'] = 'Linha {$a}: a linha tem menos colunas do que código e rótulo exigem.';
$string['csvrequired'] = 'Envie um arquivo CSV ou cole o conteúdo CSV.';
$string['csvtext'] = 'Conteúdo CSV (opcional)';
$string['dictionaries:manage'] = 'Gerenciar dicionários de referência';
$string['dictionaries:view'] = 'Ler dicionários de referência restritos pelos serviços web';
$string['domaindescription'] = 'Descrição';
$string['domaininvalid'] = 'Identificador de domínio inválido. Use apenas letras minúsculas, números e sublinhado.';
$string['domainname'] = 'Nome do domínio';
$string['domainrequired'] = 'O identificador curto do domínio é obrigatório.';
$string['domainsaved'] = 'Domínio salvo: {$a}';
$string['domainshortname'] = 'Identificador curto do domínio';
$string['domaintoolong'] = 'O identificador curto do domínio tem mais de {$a} caracteres.';
$string['domainvisibility'] = 'Quem pode consultar os itens';
$string['domainvisibility_help'] = 'Quem pode resolver e pesquisar os itens deste domínio pelos serviços web (campos de autocompletar, por exemplo). Domínios restritos exigem a capacidade local/dictionaries:view. Plugins que chamam a API PHP diretamente decidem por conta própria.';
$string['domainvisibilityinvalid'] = 'Escolha quem pode consultar os itens.';
$string['editdomain'] = 'Editar {$a}';
$string['errorcodecollision'] = 'O banco de dados já tem um código igual a "{$a}" pelas regras da sua collation (por exemplo "ß" e "ss"). Renomeie um deles.';
$string['errorcodetoolong'] = 'O código do item {$a} tem mais de 255 caracteres.';
$string['errornotables'] = 'As tabelas dos dicionários de referência ainda não estão disponíveis. Execute a atualização do plugin.';
$string['errorparentcycle'] = 'O item {$a} faz parte de um ciclo de itens pai.';
$string['errorparentself'] = 'O item {$a} não pode ser pai de si mesmo.';
$string['errorunknowndomain'] = 'Domínio desconhecido: {$a}';
$string['errorunknownparent'] = 'Códigos de item pai desconhecidos: {$a}';
$string['eventdomaincreated'] = 'Domínio de dicionário de referência criado';
$string['eventdomainupdated'] = 'Domínio de dicionário de referência atualizado';
$string['eventitemsimported'] = 'Itens de dicionário de referência importados';
$string['existingdomains'] = 'Domínios existentes';
$string['importcsv'] = 'Importar CSV';
$string['importitems'] = 'Importar itens';
$string['importsummary'] = 'Importação concluída. Inseridos: {$a->inserted}, Atualizados: {$a->updated}, Ignorados: {$a->ignored}, Duplicados: {$a->duplicates}, Rejeitados: {$a->rejected}.';
$string['itemcount'] = 'Itens';
$string['managedictionaries'] = 'Gerenciar dicionários de referência';
$string['maxbatchsizeexceeded'] = 'Tamanho máximo do lote excedido ({$a}).';
$string['name'] = 'Nome';
$string['nodomains'] = 'Nenhum domínio ainda.';
$string['parentdomain'] = 'Domínio pai (opcional)';
$string['parentdomain_help'] = 'Identificador curto do domínio que guarda os itens citados na coluna parentcode, por exemplo os estados de uma lista de municípios. Vazio significa que os pais são itens do próprio domínio importado.';
$string['placeholderdomainname'] = 'Diretorias regionais';
$string['placeholderdomainshortname'] = 'diretoria';
$string['pluginname'] = 'Dicionários de referência';
$string['privacy:metadata'] = 'O plugin Dicionários de referência guarda listas de códigos cadastradas por administradores. Não guarda dados pessoais.';
$string['savedomain'] = 'Salvar domínio';
$string['shortname'] = 'Identificador curto';
$string['timemodified'] = 'Hora de modificação';
$string['visibilityloggedin'] = 'Qualquer usuário autenticado, exceto visitantes';
$string['visibilityrestricted'] = 'Somente usuários com a capacidade de leitura';
