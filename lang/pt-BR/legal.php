<?php

return array (
  'document_label' => 'Informações legais sobre o ranking de ídolos',
  'footer_navigation' => 'Documentos legais',
  'footer_privacy' => 'política de Privacidade',
  'footer_terms' => 'Termos de Serviço',
  'effective_date_label' => 'Data de entrada em vigor',
  'effective_date_placeholder' => '[O operador deve completar: AAAA-MM-DD]',
  'privacy_title' => 'política de Privacidade',
  'privacy_seo_title' => 'Política de Privacidade | IdolRanking',
  'privacy_seo_description' => 'Saiba como o IdolRanking lida com dados de conta, login do Google, cookies, análises de visitantes, publicações públicas e solicitações de privacidade.',
  'privacy_intro' => 'Esta política explica as informações que o IdolRanking processa para operar o serviço, por que elas são usadas, por quanto tempo são retidas, quais provedores podem processá-las e como as solicitações podem ser feitas. Os itens entre colchetes devem ser verificados e preenchidos pelo operador antes da publicação.',
  'privacy_sections' =>
  array (
    0 =>
    array (
      'title' => '1. Operador e contato',
      'items' =>
      array (
        0 => 'Operador do serviço: [Revisão do operador necessária: nome da entidade jurídica ou pessoa física e dados de registro]',
        1 => 'Endereço comercial: [O operador deve preencher: endereço postal completo]',
        2 => 'Responsável pela privacidade ou contato apropriado: [O operador deve preencher: nome, cargo e método de contato]',
        3 => 'E-mail para contato relacionado à privacidade: help@idolranking.com — [O operador deve confirmar que esta caixa de entrada é monitorada e atribuir um responsável]',
      ),
    ),
    1 =>
    array (
      'title' => '2. Informações processadas e como são coletadas',
      'paragraphs' =>
      array (
        0 => 'O cadastro por e-mail processa um endereço de e-mail, um hash de senha, um apelido escolhido, um código de país selecionado, o status e a hora da verificação do e-mail e os registros de data e hora da conta. O serviço não armazena a senha em texto simples. A atribuição de indicação também pode ser armazenada quando um membro se cadastra por meio de uma indicação.',
        1 => 'Se você escolher o login do Google, o serviço solicitará os escopos openid, email e profile. Ele receberá um identificador de conta do Google e um endereço de email verificado para vincular ou criar uma conta. Um nome de exibição do Google pode ser usado como sugestão de apelido temporário durante o cadastro; ele não será publicado automaticamente no lugar do apelido que você escolher.',
        2 => 'O serviço processa publicações de fãs enviadas pelos membros, comentários, mensagens de presença, curtidas e recomendações, votos, solicitações de registro de ídolos/grupos, escolhas de idioma, imagens de perfil e campos de perfil público, como apelido, código de membro, país e imagem de perfil, para fornecer esses recursos.',
        3 => 'Para operação e segurança do serviço, o aplicativo registra os caminhos das requisições, as strings do User-Agent, a associação de membros e os registros de data e hora. Os registros de acesso à página pública não armazenam endereços IP brutos, mas os históricos de tentativas de login e os registros de solicitações de redefinição de senha armazenam endereços IP e strings do User-Agent. Os registros de hospedagem ou do servidor web também podem conter endereços IP; seu conteúdo exato e período de retenção dependem da configuração de hospedagem: [O operador deve verificar os registros e o período de retenção reais].',
        4 => 'A verificação de e-mail e a recuperação de senha podem processar o endereço de e-mail relevante, o estado de verificação, um token criptografado com prazo de validade, o horário da solicitação e o endereço IP da solicitação. A senha em texto simples e o token não são armazenados.',
      ),
    ),
    2 =>
    array (
      'title' => '3. Objetivos',
      'items' =>
      array (
        0 => 'Cadastro e login de conta por e-mail ou Google, verificação de e-mail, recuperação de senha e segurança da conta.',
        1 => 'Fornecer e moderar perfis, publicações de fãs, comentários, mensagens de presença, curtidas, recomendações e solicitações de registro de ídolos/grupos.',
        2 => 'Administrar recompensas de Corações e votação de ídolos/grupos, detectar atividades duplicadas ou abusivas e responder a solicitações de suporte.',
        3 => 'Medição de visitas a páginas públicas e confiabilidade do serviço. As estatísticas diárias de visitantes utilizam o limite de data Ásia/Seul.',
        4 => 'Traduzir publicações de fãs, comentários ou mensagens de presença quando um usuário solicita a tradução e armazenar o resultado em cache para exibição.',
        5 => 'Cumprir a lei, analisar relatórios de direitos, resolver disputas e proteger a segurança do sistema.',
      ),
    ),
    3 =>
    array (
      'title' => '4. Cookies, sessões e análise de visitantes',
      'paragraphs' =>
      array (
        0 => 'O serviço utiliza um cookie de sessão para manter o estado de login, proteção contra CSRF, seleção de idioma e o estado da requisição relacionada. O armazenamento e a expiração da sessão dependem da configuração atual do servidor. Um cookie separado armazena os horários de visualização recentes das publicações de fãs para limitar a contagem de visualizações duplicadas; ele dura até uma hora.',
        1 => 'As estatísticas de visitantes utilizam o cookie primário idolranking_visitor, que armazena um identificador aleatório e atualmente tem um tempo de vida configurado de dois anos. O UV contabiliza um identificador uma vez por dia; o PV contabiliza as navegações válidas em páginas HTML públicas. Administradores, contas fictícias e bots identificados por marcadores de User-Agent são excluídos dos totais de UV/PV. A classificação por User-Agent é imperfeita e não consegue identificar todas as solicitações automatizadas.',
        2 => 'O identificador do visitante não é derivado de um endereço IP. Registros de segurança de login separados podem conter endereços IP. Restringir cookies no seu navegador pode afetar o login, o idioma salvo ou a capacidade de distinguir visitantes únicos.',
      ),
    ),
    4 =>
    array (
      'title' => '5. Fornecedores terceirizados e transferências internacionais',
      'paragraphs' =>
      array (
        0 => 'O Google OAuth é usado para fazer login na conta do Google. Quando um usuário solicita uma tradução, o texto a ser traduzido e as informações de idioma são enviados para a API de Tradução do Google Cloud. Se uma publicação de fã incluir uma miniatura de vídeo do YouTube, o navegador poderá solicitar a imagem diretamente dos servidores de imagens do YouTube.',
        1 => 'Os e-mails de verificação e recuperação de senha podem ser enviados pelo provedor de e-mail configurado para produção. O aplicativo e o banco de dados estão hospedados no ambiente Hostinger. As entidades legais do provedor, as categorias de dados, os locais, a retenção, os subprocessadores e as salvaguardas de transferência devem ser preenchidos com base nos contratos e configurações de produção vigentes: [O operador deve preencher o provedor, o país e as salvaguardas]. Os sites externos vinculados ao serviço são regidos por seus próprios avisos de privacidade.',
      ),
    ),
    5 =>
    array (
      'title' => '6. Retenção e exclusão',
      'paragraphs' =>
      array (
        0 => 'As informações da conta e o conteúdo associado são mantidos conforme necessário para operar a conta e manter os registros de serviço. O aplicativo atual não oferece um recurso de encerramento de conta por autoatendimento. O processo para receber e lidar com solicitações de encerramento/exclusão, publicações relacionadas, registros de curtidas/votos/denúncias e quaisquer registros retidos por lei é [A critério do operador: escopo, exceções, prazos e contato responsável]. Registros exigidos por lei ou para proteger outras pessoas podem ser mantidos separadamente ou com acesso restrito, de acordo com as regras aplicáveis.',
        1 => 'O aplicativo está configurado para excluir linhas do registro de acesso à página pública após 90 dias. Não foi encontrada nenhuma limpeza automática para históricos de login, registros de IP de solicitações de redefinição de senha ou agregados diários de UV/PV e identificadores de visitantes; seus períodos exatos de retenção e tarefas de exclusão são [O operador deve definir por tipo de registro]. Os caches de tradução podem permanecer enquanto o conteúdo de origem estiver disponível.',
        2 => 'As regras de retenção e descarte para evidências de relatórios de direitos e arquivos carregados são [O operador deve preencher: retenção por tipo de arquivo, acesso autorizado e método de descarte].',
      ),
    ),
    6 =>
    array (
      'title' => '7. Seus direitos e solicitações',
      'paragraphs' =>
      array (
        0 => 'Atualmente, os membros podem editar algumas informações do perfil e a senha na página "Minha Página". Para solicitar acesso, correção, exclusão, restrição ou revogação do consentimento, conforme a legislação aplicável, entre em contato com o responsável pela privacidade após a confirmação do canal e do processo pelo operador. Podemos verificar a identidade e limitar uma solicitação caso os direitos de outra pessoa ou uma obrigação legal de retenção de dados sejam afetados.',
        1 => 'A funcionalidade de autoatendimento para exclusão de conta e o prazo para resposta a solicitações não estão implementados na aplicação atual. Antes da publicação, o operador deve definir o canal de entrada real, as verificações de identidade, as regras de tratamento e o tempo de resposta: [Ação do operador necessária].',
      ),
    ),
    7 =>
    array (
      'title' => '8. Conteúdo público e perfis',
      'paragraphs' =>
      array (
        0 => 'Publicações públicas, comentários e campos de perfil, como apelido, país e imagem de perfil, podem ser visíveis para visitantes não autenticados e mecanismos de busca. Endereços de e-mail, hashes de senha e registros de login/segurança não são exibidos em perfis públicos. Após o conteúdo ser excluído ou ocultado, cópias podem permanecer temporariamente em backups, evidências de direitos autorais, caches ou registros mantidos por lei; o operador deve definir esses períodos.',
      ),
    ),
    8 =>
    array (
      'title' => '9. Medidas de segurança',
      'paragraphs' =>
      array (
        0 => 'O serviço utiliza criptografia de senha, proteção contra falsificação de requisições, regeneração de ID de sessão após o login, verificações de autorização e armazenamento separado de arquivos públicos e privados. A organização de segurança responsável, o processo de notificação de incidentes, o cronograma de revisão de acesso e o contato em caso de violação de segurança são [A ser verificado pelo operador]. Nenhum serviço de internet pode eliminar todos os riscos de segurança.',
      ),
    ),
    9 =>
    array (
      'title' => '10. Alterações de contato e política',
      'paragraphs' =>
      array (
        0 => 'Contato para assuntos de privacidade: help@idolranking.com ([O operador deve confirmar se este é um endereço oficial monitorado]). Não envie senhas ou tokens de autenticação. Forneça apenas as informações mínimas da conta necessárias para processar sua solicitação. O contato responsável, endereço, legislação aplicável, prazo de resposta e processo de notificação de alterações são [O operador deve preencher].',
        1 => 'Quando esta política for alterada, o serviço atualizará esta página e sua data de vigência. O operador deve determinar como o aviso prévio e qualquer renovação de consentimento legalmente exigida serão tratados: [Revisão do operador necessária].',
      ),
    ),
  ),
  'terms_title' => 'Termos de Serviço',
  'terms_seo_title' => 'Termos de Serviço | IdolRanking',
  'terms_seo_description' => 'Leia as regras para contas do IdolRanking, publicações de fãs, comentários, pedidos de ídolos, recompensas de corações, votação e uso do serviço.',
  'terms_intro' => 'Estes termos descrevem as funcionalidades atualmente oferecidas pelo IdolRanking e as responsabilidades básicas dos usuários e do operador. Os detalhes legais e operacionais entre parênteses devem ser definidos pelo operador.',
  'terms_sections' =>
  array (
    0 =>
    array (
      'title' => '1. Serviço e operador',
      'paragraphs' =>
      array (
        0 => 'IdolRanking é um serviço comunitário que oferece rankings de ídolos/grupos, publicações e comentários de fãs, mensagens de presença, recompensas de corações e votação, além de solicitações de registro de ídolos/grupos.',
        1 => 'Operador: [Revisão do operador necessária: nome da entidade legal ou pessoa física]; endereço: [O operador deve preencher]; contato: help@idolranking.com ([O operador deve confirmar se este é um canal de contato oficial]).',
      ),
    ),
    1 =>
    array (
      'title' => '2. Contas e elegibilidade',
      'paragraphs' =>
      array (
        0 => 'O cadastro por e-mail requer verificação de e-mail. O cadastro pelo Google utiliza um endereço de e-mail verificado pelo Google e um identificador vinculado à conta. Os membros devem fornecer informações precisas e proteger suas credenciais. Ao se cadastrar pelo Google, o membro escolhe seu próprio apelido e país.',
        1 => 'A idade mínima e os requisitos de consentimento do responsável legal são [a serem definidos pelo operador para cada jurisdição aplicável]. Não apresente o serviço como adequado para uma faixa etária específica até que essas regras sejam confirmadas.',
      ),
    ),
    2 =>
    array (
      'title' => '3. Responsabilidades dos membros e conduta proibida',
      'items' =>
      array (
        0 => 'Utilizar a conta de outra pessoa ou contornar a segurança da conta, a autenticação, as restrições de acesso ou os controles de serviço.',
        1 => 'Utilizar automação, múltiplas contas ou atividades coordenadas para manipular recompensas de corações, votos, classificações ou estatísticas de visitantes, ou para obter um benefício indevido.',
        2 => 'Publicar material ilegal, fraudulento, ameaçador, assediador, odioso, difamatório, invasivo da privacidade ou abusivo e direcionado.',
        3 => 'Enviar material que viole direitos autorais, marcas registradas, direitos de imagem, privacidade ou os termos de um serviço de terceiros.',
        4 => 'Coleta ou extração excessiva de dados, busca por vulnerabilidades, transmissão de código malicioso ou interrupção de sistemas.',
      ),
    ),
    3 =>
    array (
      'title' => '4. Publicações de fãs, comentários e conteúdo do usuário',
      'paragraphs' =>
      array (
        0 => 'Os membros devem possuir os direitos e permissões necessários para Publicações de Fãs, comentários, mensagens de presença, imagens e solicitações de inscrição, e devem respeitar a lei e os direitos de terceiros. O autor mantém seus direitos; a publicação não transfere a propriedade para o operador.',
        1 => 'Para operar o serviço, o membro concede ao operador uma permissão não exclusiva e limitada ao serviço para armazenar e fazer backup do conteúdo, convertê-lo para os formatos necessários, transmiti-lo e exibi-lo aos usuários e visitantes do serviço. O término exato desta permissão e a duração das cópias de backup/comprovação requerem [análise jurídica do operador]. Este texto não autoriza o uso para fins publicitários ou de venda fora do serviço.',
        2 => 'Um pedido de registro de ídolo/grupo é uma proposta para análise, não uma promessa de aprovação, tempo de registro ou recompensa específica. O operador pode analisar, editar ou recusar um pedido por questões de precisão, duplicidade ou direitos autorais.',
      ),
    ),
    4 =>
    array (
      'title' => '5. Direitos autorais, direitos de imagem, denúncias e moderação',
      'paragraphs' =>
      array (
        0 => 'Não publique trabalhos, fotografias, imagens ou informações pessoais de terceiros sem permissão. O serviço oferece recursos de denúncia para publicações/comentários de fãs e imagens de perfil. Inclua o conteúdo específico e o motivo da denúncia. O operador poderá analisar o material e ocultá-lo, restaurá-lo ou removê-lo, ou solicitar mais informações.',
        1 => 'Os detalhes obrigatórios relativos à notificação legal, prazos de resposta, recursos e procedimentos para infratores reincidentes são [a serem preenchidos pelo operador de acordo com a legislação aplicável]. As ferramentas de denúncia implementadas não pretendem substituir todos os processos legais de notificação e remoção.',
      ),
    ),
    5 =>
    array (
      'title' => '6. Recompensas por corações e votação',
      'paragraphs' =>
      array (
        0 => 'Corações são uma moeda virtual usada como recompensa por atividades e votação de ídolos/grupos. Não possuem valor monetário, não podem ser resgatados, transferidos ou comprados. Os tipos de recompensa, faixas de valores, limites diários e critérios de elegibilidade seguem as configurações do recurso e do administrador; essas configurações podem alterar as condições de recompensa no futuro.',
        1 => 'Os votos são processados de acordo com as regras de votação vigentes e o saldo de Corações disponível. Recompensas ou votos obtidos por meio de automação, duplicação ou atividade fraudulenta podem ser revisados, corrigidos ou invalidados. O processo de notificação e apelação para recompensas indevidas é [Revisão pelo operador necessária: definir critérios de tratamento].',
      ),
    ),
    6 =>
    array (
      'title' => '7. Medidas de conteúdo e de conta',
      'paragraphs' =>
      array (
        0 => 'Por motivos legais, de proteção de direitos, segurança ou suspeita de violação, o operador poderá ocultar ou remover publicações, comentários, imagens ou solicitações de cadastro, ajustar recompensas ou restringir/suspender o acesso à conta. Sempre que possível, o operador fornecerá uma justificativa e um meio de contato; casos urgentes de segurança ou questões legais podem exigir ação imediata.',
        1 => 'A duração da suspensão, o canal de apelação, o encerramento/exclusão da conta a pedido do membro e o gerenciamento de Corações não utilizados são [a serem definidos pelo operador]. O aplicativo atual não oferece um recurso de autoatendimento para exclusão de conta.',
      ),
    ),
    7 =>
    array (
      'title' => '8. Alterações e disponibilidade do serviço',
      'paragraphs' =>
      array (
        0 => 'O operador poderá alterar ou descontinuar funcionalidades, telas, configurações de recompensas ou exibição de conteúdo por motivos legais, de segurança, técnicos ou operacionais. Manutenções ou interrupções podem afetar temporariamente o serviço. Serviços externos, como Google, provedores de e-mail e YouTube, são controlados por seus respectivos provedores.',
        1 => 'Na medida permitida pela legislação aplicável, a responsabilidade pode ser limitada por falhas externas, conduta do usuário ou eventos fora do controle razoável da operadora. Nada aqui limita os direitos obrigatórios do consumidor ou os direitos à privacidade. A legislação aplicável e os termos finais de responsabilidade exigem [revisão jurídica da operadora].',
      ),
    ),
    8 =>
    array (
      'title' => '9. Registros de alterações e aceitação',
      'paragraphs' =>
      array (
        0 => 'As alterações serão publicadas nesta página com seu conteúdo e data de vigência. O operador deve definir o aviso prévio, quando as alterações entrarão em vigor para os membros existentes e quando será necessário um novo consentimento: [A ser finalizado pelo operador]. O aplicativo atual não parece registrar a aceitação por versão da política, portanto, o operador deve decidir como a aceitação será apresentada e registrada.',
      ),
    ),
    9 =>
    array (
      'title' => '10. Contato',
      'paragraphs' =>
      array (
        0 => 'Para termos, denúncias de conteúdo ou restrições de conta: help@idolranking.com ([O operador deve confirmar a pessoa responsável, a caixa de entrada monitorada e o processo de resposta]). A identidade do operador, o endereço comercial, a legislação aplicável, o foro competente para resolução de disputas e o prazo de resposta devem ser preenchidos antes da publicação: [Ação do operador necessária].',
      ),
    ),
  ),
);
