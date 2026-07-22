# KodeWeb Lite

Irmão mais novo do [KodeWeb IDE](https://github.com/laraantunes/kodeweb), o KodeWeb Lite é um ambiente de desenvolvimento web integrado (IDE) leve com foco em ser acessado via celulares, desenvolvido em PHP, HTML, CSS e JavaScript. Ele fornece ferramentas essenciais para gerenciar projetos, acessar servidores e editar código diretamente do navegador, sem a necessidade de configurações complexas.

## 🚀 Funcionalidades

- **Gerenciador de Arquivos Locais**: Navegue, crie, edite, renomeie e exclua arquivos no seu workspace local.
- **Conexões FTP**: Conecte-se a servidores remotos via FTP, com suporte à edição e manipulação direta de arquivos.
- **Editor de Código Avançado**: Integração com o Ace Editor oferecendo *syntax highlighting*, autocompletar e muito mais.
- **Gerenciador de Banco de Dados**: Explore e execute queries em bancos de dados MySQL e SQLite diretamente da interface.
- **Terminais Integrados**:
  - *Terminal Local*: Execute comandos no ambiente em que o KodeWeb Lite está hospedado.
  - *Terminal SSH*: Conecte-se a servidores remotos via SSH e execute comandos em uma aba dedicada.
- **Sistema de Abas (Tabs)**: Trabalhe em múltiplos arquivos e terminais simultaneamente. Feche abas facilmente utilizando o clique do meio do mouse (scroll).
- **Auto-Updater**: Mantenha o sistema sempre atualizado através da integração com as *releases* do repositório no GitHub.
- **Visual Moderno e Responsivo**: Interface elegante inspirada em IDEs modernas, com tema escuro e transições suaves.

## 🛠️ Instalação

1. Faça o download da última *release* na [página de lançamentos](https://github.com/laraantunes/kodeweblite/releases).
2. Extraia os arquivos em um diretório do seu servidor web (ex: Apache, Nginx) que tenha suporte a PHP (recomendado PHP 8.0+).
3. Acesse o diretório através do seu navegador (ex: `http://localhost/kodeweb-lite`).
4. Na primeira execução, você será guiado a criar um usuário mestre e definir o caminho do seu workspace inicial.
5. Para atualizações posteriores, é só abrir o menu `Opções` -> `Sobre` na interface e clicar em **Buscar Atualizações**.

## 🔒 Segurança

Por se tratar de uma ferramenta poderosa que concede acesso a arquivos do sistema, banco de dados e execução de comandos (Terminais), é **fortemente recomendado** que o KodeWeb Lite não fique exposto publicamente sem camadas extras de proteção (como VPNs, autenticação HTTP básica no servidor web ou restrição de IPs).

## 💻 Tecnologias Utilizadas

- **Backend**: PHP (sem frameworks externos para manter a leveza)
- **Frontend**: HTML5, Vanilla CSS e Vanilla JavaScript
- **Editor**: [ACE Editor](https://ace.c9.io/)
- **Banco de Dados**: PDO (PHP Data Objects) para MySQL e SQLite
- **Ícones**: Estilo minimalista com SVG/Emojis
- **Composer**: Com bibliotecas já pré carregadas na release, é só baixar e rodar! :)

## 🤝 Contribuição

Contribuições são bem-vindas! Sinta-se à vontade para abrir *Issues* relatando bugs ou *Pull Requests* sugerindo melhorias.

## 📄 Licença

Criado e mantido por [Laralabs](https://laralabs.dev).
