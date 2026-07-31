-- Correções ortográficas automáticas (fix-ortografia.mjs)
-- Fonte: sqls/receitas-backup-2026-07-28.sql
-- Receitas: 40 | Com correções: 18

UPDATE `receitas` SET
  `titulo` = 'Torta de ricota',
  `descricao` = 'Torta de ricota  Deliciosa.',
  `modo_preparo` = 'Peneire a ricota e junte a todos os ingredientes menos as claras e bata na primeira velocidade e reserve. Bata as claras na terceira velocidade e vá adicionando lentamente o restante do ingredientes.',
  `observacoes` = '',
  `ingredientes` = '{"gema":"16% - 400g","clara":"24% - 600g","açúcar":"60% - 1500g","uvas-passas":"8% - 200g","canela-em-pó":"0.2% - 5g","ricota fresca":"100% - 2500g","essência de limão":"QB","creme de confeiteiro":"20% - 500g","frutas cristalizadas":"8% - 200g"}'
WHERE `id` = 'torta-de-ricota';

UPDATE `receitas` SET
  `titulo` = 'Queijadinha',
  `descricao` = 'Receita importada de 08_12_2009_Queijadinha.doc',
  `modo_preparo` = 'Coloque em uma panela , o açúcar, água e a margarina e leve ao fogo       e deixe ferver, esfrie a mistura até que ela fique morna e adicione o restante dos ingredientes e espere o ponto. Forre as formas com papel antes de colocar a massa, e leve ao forno por mais ou menos 20 minutos a uma temperatura de 200 graus.',
  `observacoes` = '',
  `ingredientes` = '{"água":"50% - 250g","ovos":"20% - 100g","gemas":"20% - 100g","açúcar":"100% - 500g","margarina":"10% - 50g","queijo-parmesão":"100% - 500g","coco-seco-açucarado":"80% - 400g"}'
WHERE `id` = 'queijadinha';

UPDATE `receitas` SET
  `titulo` = 'Bolo de fubá',
  `descricao` = 'Tradicional Bolo de Fubá. ',
  `modo_preparo` = 'Junte a margarina, açúcar, leite e a erva doce e leve ao fogo para ferver, e quando ferver junte o fubá e deixe cozinhar muito bem. Após coloque na batedeira  e acescente os ovos de vagar e depois a farinha e o fermento   e deixe bater na terceira velocidade até obter a massa no ponto certo. Coloque a massa em um tabuleiro untado com margarina e polvilhado com farinha, e  leve ao forno por mais ou menos 25 minutos a uma temperatura de 200 graus.',
  `observacoes` = '',
  `ingredientes` = '{"Ovos":"88% - 792g","Fubá":"100% - 900g","Leite":"133% - 1197g","Açúcar":"111% - 999g","Erva doce":"5% - 45g","Margarina":"33% - 297g","Farinha de trigo":"24% - 216g","Fermento em pó químico":"6% - 54g"}'
WHERE `id` = 'bolo-de-fuba';

UPDATE `receitas` SET
  `titulo` = 'Bolo frapê',
  `descricao` = 'Bolo frapê um bolo bicolor. ',
  `modo_preparo` = 'Coloque em uma batedeira 250 gramas de farinha  margarina e açúcar e   acrescentar o restante dos ovos e bater até cremar. Acrescentar farinha fermento  e o amido deixando o leite por último bata mais 5 minutos. Separe 1/3 da massa acrescente o chocolate e misture a mão para obter um bolo do tipo frapê coloque sem chocolate em uma forma de pão de forma e  o restante por cima  e leve ao forno mais ou menos 20 minutos a uma temperatura de 180° graus.',
  `observacoes` = '',
  `ingredientes` = '{"Ovos":"47% - 705g","Leite":"63% - 945g","Açúcar":"81% - 1215g","Biscomil":"5% - 75g","Margarina":"31% - 465g","Chocolate em pó":"5% - 75g","Farinha de trigo":"100% - 1500g","Amido de milho biscomil":"5% - 75g","Fermento em pó químico":"5% - 75g"}'
WHERE `id` = 'bolo-frape';

UPDATE `receitas` SET
  `titulo` = 'Biscoito areia de Cascais',
  `descricao` = 'Receita importada de 16_11_2009_biscoito_areia_caiscais.doc',
  `modo_preparo` = 'Coloque farinha de trigo sobre a mesa e façauma cova e coloque o açúcar e a margarina misture com as mãos até ficar uma massa homogênea faça bolas de 1 centímetro de diâmetro arrume em tabuleiro  levemente untada com óleo, leve ao forno por mais ou menos  15 minutos a uma temperatura 160 graus.',
  `observacoes` = '',
  `ingredientes` = '{"Açúcar":"40%","Margarina":"67%","Farinha de trigo":"100%"}'
WHERE `id` = 'biscoito-areia-de-caiscais';

UPDATE `receitas` SET
  `titulo` = 'Carinhoso',
  `descricao` = 'Biscoito muito delicado.',
  `modo_preparo` = 'Sobre um mesa misture o amido com  farinha abra uma cova e coloque açúcar e margaria amasse a obter uma  lisa (caso necessário coloque o leite de coco para não deixar a massa seca) divida a massa com um cortador redondo e  arrume a massa já modelada em uma bandeja e leve ao forno por mais ou menos 15 minutos  a  uma temperatura de 180 graus.',
  `observacoes` = '',
  `ingredientes` = '{"carinhoso":{"açúcar":"50% - 500g","margarina":"100% - 1000g","coco-ralado":"30% - 300g","amido-de-milho":"100% - 1000g","farinha-de-trigo":"70% - 700g"},"caso-necessario":{"leite-de-coco":"20% - 200g"}}'
WHERE `id` = 'carinhoso';

UPDATE `receitas` SET
  `titulo` = 'Pão de forma',
  `descricao` = 'Pão (Plus Vita). ',
  `modo_preparo` = 'Coloque na masseira todos os ingredientes secos de bater 1 minuto, depois coloque o restante dos ingredientes e deixe bater mais ou menos 5 minutos ou até a massa ficar lisa e homogênea. Retire a massa da masseira  e coloque sobre uma mesa untada com óleo e corte em pedaços de 550 gramas modele e ponha em forma para pão de forma e deixe descansar mais ou menos 120 minutos ou até dois dedos para encostar na tampa, e leve ao forno por mais ou menos 30 minutos a uma temperatura de 180 graus.',
  `observacoes` = '',
  `ingredientes` = '{"sal":"2% - 100g","água":"54% - 2700g","ovos":"4% - 200g","açúcar":"10% - 500g","margarina":"300g","reforcador":"1% - 50g","fermento-fresco":"6% - 300g","farinha-de-trigo":"100% - 5000g"}'
WHERE `id` = 'pao-de-forma';

UPDATE `receitas` SET
  `titulo` = 'Pão de batata',
  `descricao` = 'Pão com uma das massas mais leves da panificação.',
  `modo_preparo` = 'Coloque 1 quilo e meio de farinha com todo o fermento da receita na masseira e faça uma  esponja e deixe descansar por 20 minutos coberta com plástico.',
  `observacoes` = '',
  `ingredientes` = '{"massa-reforço":{"Sal":"2% - 80g","Ovos":"8% - 320g","Água":"30% - 1200g","Açúcar":"15% - 600g","Margarina":"8% - 320g","Batata cozida":"30% - 1200g","Farinha de trigo":"62.5% - 2500g"},"pão-de-batata-ingredientes-massa-esponja":{"Fermento":"6% - 240g","Farinha de trigo":"37.5% - 1500g"}}'
WHERE `id` = 'pao-de-batata';

UPDATE `receitas` SET
  `titulo` = 'Panetone',
  `descricao` = 'Muito comum no Natal.',
  `modo_preparo` = 'Misture a primeira massa com a segunda e bata até ficar lisa e homogênea, e depois misture as  frutas ainda na masseira, e jogue toda massa em uma mesa untada com óleo, abra toda a massa e coloque as uvas passa no meio enrole como rocambole, e  corte em pedaços de 520 gramas e ponha na forma e deixe descansar por 3 horas ou até encostar  na borda da forma, e leve ao forno mais ou menos 40 minutos a uma temperatura de 180° graus.',
  `observacoes` = '',
  `ingredientes` = '{"massa-de-reforço":{"Sal":"1% - 125g","Gema":"18% - 2250g","Água":"24% - 3000g","Pronamix":"40% - 5000g","Uvas-passa":"40% - 5000g","Farinha de trigo":"60% - 7500g","Frutas cristalizadas":"40% - 5000g"},"panetone-massa-esponja":{"Água":"22% - 2750g","Fermento":"7.2% - 900g","Farinha de trigo":"40% - 5000g"}}'
WHERE `id` = 'panetone';

UPDATE `receitas` SET
  `titulo` = 'Biscoito de polvilho azedo',
  `descricao` = 'Derrete na boca.',
  `modo_preparo` = 'Junte a primeira parte leve ao fogo fazendo um farofa  e em seguida junte a primeira parte com a segunda parte em uma batedeira e deixe os ovos por último  para dar ponto. Retire da batedeira e coloque em um saco ou manga de confeitar, e com um bico frisado ou liso pingue em um tabuleiro e leve ao forno a uma temperatura de  200° mais ou menos 20 minutos .',
  `observacoes` = '',
  `ingredientes` = '{"segunda-parte":{"Sal":"10% - 100g","Ovos":"10% - 100g","Leite":"100% - 1000g","Óleo":"100% - 1000g"},"primeira-parte":{"Água":"100% - 1000g","Polvilho azedo":"10% - 100g"}}'
WHERE `id` = 'biscoito-de-polvilho-azedo';

UPDATE `receitas` SET
  `titulo` = 'Minuta de chocolate',
  `descricao` = 'Mais uma versão daquele gostinho de chocolate.',
  `modo_preparo` = 'Bata o açúcar com a margarina até formar um creme e em seguida coloque a farinha, o chocolate, o fermento todos misturados deixando por último os ovos para dar ponto cortar pedaços de 30 gramas e faça bolas e passe no açúcar cristalizado e',
  `observacoes` = '',
  `ingredientes` = '{"Ovos":"25% - 250g","Açúcar":"35% - 350g","Margarina":"30% - 300g","Chocolate em pó":"20% - 200g","Farinha de trigo":"100% - 1000g","Fermento em pó químico":"5% - 50g"}'
WHERE `id` = 'minuta-de-chocolate';

UPDATE `receitas` SET
  `titulo` = 'Pão doce',
  `descricao` = 'Dipensa apresentação.',
  `modo_preparo` = 'Coloque todos os ingredientes secos na masseira e deixe bater 1 minutos, em seguida coloque a água e deixa bater 5 minutos. Após bater coloque a massa sobre uma mesa untada com óleo e corte em pedaços de 2 quilos e passe na cortadora para pães pequenos em corte em pedaços de 300 gramas para diversos. Modele  e arrume em bandeja levemente untada com óleo  sobre o regime de arrumação 10 por 3 caso os pães  sejam pequenos, pinte os pães com gema de ovo e leve ao armário e deixe descansar por mais ou menos 120 minutos ou até dobrar de tamanho, antes de levar ao forno coloque recheio externo	 e abertura, leve  para o forno por mais ou menos 20 minutos a uma temperatura de   160 graus.\n\nLeve ao fogo 500 gramas de Leite e 200 gramas de açúcar uma pitada de corante deixar ferver. Reserve 200 gramas de farinha e 200 gramas de açúcar e misture vigorosamente quando o leite começar a ferver para não empelotar.',
  `observacoes` = '',
  `ingredientes` = '{"pão-doce":{"Sal":"2% - 100g","Ovos":"6% - 300g","Água":"50% - 2500g","Açúcar":"20% - 1000g","Fermento":"6% - 300g","Margarina":"8% - 400g","Reforcador":"1% - 50g","Farinha de trigo":"100% - 5000g"},"creme-para-pão-doce":{"Leite":"100% - 500g","Corante":"QB","Farinha":"40% - 200g"}}'
WHERE `id` = 'pao-doce';

UPDATE `receitas` SET
  `titulo` = 'Brevidade',
  `descricao` = 'Bolo leve e gostoso.',
  `modo_preparo` = 'Coloque em uma bacia todos os ingredientes deixando o leite por último para estabelecer o ponto adequado da massa, Bata a vá adicionando o leite até ela chegar no ponto, e em seguida use um saco ou  manga de confeitar com bico liso ou frisado para arrumar a massa em um tabuleiro levemente untado com óleo, após ter terminado todos os processos anteriores leve a massa ao forno mais ou menos 20 minutos a uma temperatura de 180 graus.',
  `observacoes` = '',
  `ingredientes` = '{"Ovos":"25% - 375g","Leite":"50% - 750g","Açúcar":"40% - 600g","Essência":"QB","Margarina":"40% - 600g","Amido de milho":"100% - 1500g","Fermento químico em pó":"1.4% - 21g"}'
WHERE `id` = 'brevidade';

UPDATE `receitas` SET
  `titulo` = 'Bolo Inglês',
  `descricao` = 'Receita importada de bolo_ingles_03_11_2009.doc',
  `modo_preparo` = '1. Coloque em uma batedeira na terceira velocidade margarina e açúcar por mais ou menos 15 minutos ou até cremar 2. reduza a velocidade da batedeira para primeira, e coloque o restante dos ingredientes deixando o leite por último . 3. Após os ingredientes misturados deixe bater por mais 5 minutos. 4. Tendo a massa atingido o ponto coloque em forma para bolo Inglês. 5. Asse no forno a 180°  por mais ou menos 15 minutos.',
  `observacoes` = '',
  `ingredientes` = '{"Ovos":"45%","Amido":"7%","Leite":"63%","Açúcar":"82%","Margarina":"32%","Farinha de trigo":"100%","Fermento pó químico":"4%"}'
WHERE `id` = 'bolo-ingles';

UPDATE `receitas` SET
  `titulo` = 'Sequilho de coco',
  `descricao` = 'Biscoito que derrete na boca.',
  `modo_preparo` = '1. junte a farinha de trigo, amido de milho e açúcar em uma bacia ou na mesa e misture bem com as mão 2. Depois coloque a margarina e o coco ralado  e continue misturando com mãos até ficar lisa e homogênea. 3. Faça bolas pequenas e arrume em bandejas sobre o regime de arrumação e marque os biscoitos com um garfo . 4. Leve ao forno a 180° (cento e oitenta graus)  mais ou menos 15 minutos .',
  `observacoes` = '',
  `ingredientes` = '{"açúcar":"50%","margarina":"100%","coco-ralado":"30%","leite-de-coco":"QB","amido-de-milho":"100%","farinha-de-trigo":"70%"}'
WHERE `id` = 'sequilho-de-coco';

UPDATE `receitas` SET
  `titulo` = 'Pão francês',
  `descricao` = 'Pão nosso de cada dia.',
  `modo_preparo` = 'Coloque na masseira todos os ingredientes secos deixe bater por 2 minuto, após bater coloque  o restante dos ingredientes de deixe bater mais 5 minutos ou até ficar lisa e homogênea,   depois de bater coloque a massa em uma mesa untada com óleo e corte em pedaços de 2 quilos,  após corte e  modele a massa para pôr  nas formas para pão francês (Grades) com as costuras voltadas para baixo e deixe descansar por mais ou menos 120 minutos, após o tempo adequado de descanso leve  ao forno por mais ou menos 15 minutos com vapor .',
  `observacoes` = '',
  `ingredientes` = '{"Sal":"2%","Água":"53%","Fermento":"3%","Reforcador":"1%","Farinha de trigo":"100%"}'
WHERE `id` = 'pao-frances';

UPDATE `receitas` SET
  `titulo` = 'Bolo mãe Benta',
  `descricao` = 'Mais umbolo gostoso.',
  `modo_preparo` = '	Coloque  em uma batedeira a margarina e açúcar, e deixe bater mais ou menos 15 minutos ou até a massa fique com um aspecto de creme, diminua a velocidade da batedeira coloque o restante dos ingredientes deixando o leite por último , após este processo aumente a velocidade da batedeira e deixe bater por mais 5 minutos. Estando a massa pronta use um saco de confeitar para colocar a massa em formas de papel de número 0 (zero),depois leve ao forno por mais ou menos 15 minutos a uma temperatura de 180 graus.   \n',
  `observacoes` = '',
  `ingredientes` = '{"Ovos":"60%","Farinha":"100%","Açúcar":"75%","Margarina":"35%","Leite líquido":"50%","Fermento em pó":"5%"}'
WHERE `id` = 'bolo-mae-benta';

UPDATE `receitas` SET
  `titulo` = 'Brioche rápido',
  `descricao` = 'Receita importada de brioche_rapido_19_10_2009.doc',
  `modo_preparo` = 'Caso o o fermento seja do tipo seco, comece misturando a farinha o o fermente antes de por a água, estando o fermento e a farinha bem misturadas, junte os outro ingredientes faça uma massa homogênea  e  deixe descansar por aproximadamente 30 minutos . Depois junte o restante da farinha, mais açúcar, sal e margarina, e faça uma massa homogênea, não esqueça da água! Misture  a primeira massa com a segunda e abra   massa  com um rolo de pastel até ficar lisa e homogênea. Depois desse  processo todo, enrole a massa como  rocambole, e corte em pedaços de aproximadamente 30 gramas, arrume em um tabuleiro sobre o regime 9 por 6.Deixe descansar em um armário fechado ou coberto por um plástico por aproximadamente 90 minutos. Após descansar leve ao forno por 15 minutos na temperatura de 160 graus.',
  `observacoes` = 'Processo massa esponja para acelerar a fermentação.',
  `ingredientes` = '{"esponja-de-reforço":{"Gema":"30g","Ovos":"50g","Farinha":"20% - 125g"},"brioche-rápido-esponja":{"Sal":"1.6% - 10g","Gema":"4.8% - 30g","Ovos":"8% - 50g","Água":"QB","Farinha":"80% - 500g","Açúcar":"12% - 75g","Fermento":"8%","Margarina":"75%"}}'
WHERE `id` = 'brioche-rapido';
