# Como formatar receitas para importacao

O parser extrai texto de arquivos `.doc`, `.docx`, `.txt` e `.md` e tenta identificar titulo, ingredientes e modo de preparo automaticamente.

---

## Formato ideal

Use TABs (tecla Tab) para separar os campos de cada ingrediente:

```
Nome do Ingrediente[TAB]Porcentagem[TAB][TAB]Quantidade[TAB]Unidade
```

### Exemplo completo

```
Pão integral

Receita passada no dia 07/12/2009

Farinha de trigo especial	50%		1500	g
farinha de trigo integral	50%		1500	g
Agua				58%		1740	g
Fermento fresco		3%		90	g
Sal				2%		60	g
Reforcador			1%		30	g

Modo de preparo

Coloque na masseira todos os ingredientes secos...
```

### Regras

1. Cada ingrediente ocupa **uma linha**
2. Campos separados por **TAB** (nao espaco)
3. A **primeira coluna** e o nome do ingrediente
4. A **segunda coluna** e a porcentagem (ex: `50%`)
5. A **terceira coluna** (opcional) e a quantidade numerica (ex: `1500`)
6. A **quarta coluna** (opcional) e a unidade (ex: `g`)

---

## Variantes aceitas

### So porcentagem (sem quantidade)

```
Farinha de trigo	100%
Margarina		67%
Acucar			40%
```

### Com quantidade em gramas

```
Farinha de trigo	50%		1500	g
Agua			58%		1740	g
```

### Decimais (use virgula ou ponto)

```
Canela em po		0,2%		5	g
Farinha de trigo	37,5%		1500	g
```

### QB - Quanto Basta

```
Essencia de limao	QB		QB
margarina		QB		QB
```

### Sub-grupos de ingredientes

Se a receita tem partes separadas (ex: "Massa esponja" e "Massa reforco"), escreva o nome do grupo sozinho em uma linha antes dos ingredientes daquele grupo:

```
Massa esponja

Farinha de trigo	37,5%		1500	g
Fermento		6%		240	g

Massa reforco

Farinha de trigo	62,5%		2500	g
Batata cozida		30%		1200	g
Agua			30%		1200	g
```

---

## Marcacao explicita (opcional)

Se quiser garantir que o parser identifique corretamente os ingredientes, adicione as palavras `ingredientes` e `fimIngredientes` no arquivo:

```
Pao doce

ingredientes

Farinha de trigo	100%		5000	g
Acucar			20%		1000	g
Sal			2%		100	g
Ovos			6%		300	g
Agua			50%		2500	g

fimIngredientes

Modo de preparo

Coloque todos os ingredientes secos na masseira...
```

**Importante:** `ingredientes` e `fimIngredientes` devem estar em linhas separadas, sozinhos (sem nada mais na linha).

---

## Modo de preparo

O parser detecta automaticamente quando comeca o modo de preparo. Procure por uma dessas linhas:

- `Modo de preparo`
- `Modo de preparo.`
- `Preparo`
- `Preparacao`
- `Instrucoes`

Tudo **depois** dessa linha e considerado o modo de preparo.

---

## Observacoes

Linhas que comecam com `Obs` ou `OBS` sao ignoradas pelo parser.

---

## Resumo do formato

```
[TITULO DA RECEITA]
receita passada no dia DD/MM/AAAA

[ingredientes]          <- opcional

[Sub-grupo]             <- opcional
Ingrediente 1[TAB]%[TAB][TAB]Qtd[TAB]un
Ingrediente 2[TAB]%[TAB][TAB]Qtd[TAB]un
Ingrediente 3[TAB]%           <- so porcentagem

[fimIngredientes]       <- opcional

Modo de preparo

[texto do modo de preparo]
```
