# Black Friday Vésteme Landing

Landing page + catálogo inspirado em Web 3.0 para a campanha "A maior black da Vésteme". Monta carrinho, calcula economia e envia cadastro para MySQL remoto.

## Estrutura principal
- `index.php`: hero + categorias.
- `category.php?slug=vestidos`: e-commerce sem checkout para cada coleção.
- `submit.php`: recebe formulário e grava no banco.
- `obrigado.php`: tela de confirmação + CTA para o grupo.
- `assets/css` e `assets/js`: estilos neon e lógicas (timer + carrinho).
- `data/catalog.php`: catálogo mock em arrays PHP.
- `sql/schema.sql`: tabela `bf_leads` para armazenar as reservas.

## Configuração do banco
1. Libere seu IP remoto no painel da Hostinger (Banco de Dados > Acesso remoto).
2. Atualize `config/config.php` com o host da instância MySQL (`YOUR_HOSTINGER_MYSQL_HOST`).
3. Execute o script `sql/schema.sql` no banco `u817857337_blackfriday` para criar a tabela `bf_leads`.

## Rodando localmente
```bash
# Dentro da pasta do projeto
php -S localhost:8000
```
Acesse `http://localhost:8000/index.php` para ver a landing. As páginas de categoria usam `category.php?slug=denim` etc.

## Fluxo do lead
1. Lojista escolhe categoria e adiciona produtos ao carrinho.
2. Ao fechar o carrinho, preenche nome, e-mail e WhatsApp.
3. `submit.php` persiste o JSON dos itens + totais e redireciona para `obrigado.php` com CTA do grupo.
4. Para editar o carrinho, basta voltar à categoria e reenviar.

## Personalização rápida
- Troque links/IDs dos vídeos diretamente no `data/catalog.php`.
- Substitua as imagens e textos dos cards conforme seu acervo real.
- Ajuste cores no `:root` de `assets/css/styles.css` para outras combinações neon.
