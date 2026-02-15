<?php
declare(strict_types=1);

class QueryBuilder
{
    private PDO $pdo;

    private string $table = '';
    private array $columns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $orderBy = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ---- Base ----
    public function table(string $table): self
    {
        $this->reset();
        $this->table = $table;
        return $this;
    }

    public function select(array|string $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    // ---- Where ----
    public function where(string $column, string $operator, mixed $value): self
    {
        $param = ':w' . count($this->bindings);
        $this->wheres[] = [$column, $operator, $param, 'AND'];
        $this->bindings[$param] = $value;
        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value): self
    {
        $param = ':w' . count($this->bindings);
        $this->wheres[] = [$column, $operator, $param, 'OR'];
        $this->bindings[$param] = $value;
        return $this;
    }

    /**
     * WHERE column IN (...)
     * Ej:
     *   ->whereIn('role', ['admin','tecnico'])
     */
    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            // Si no hay valores, lo hacemos imposible para no devolver todo
            // WHERE 1=0
            $this->wheres[] = ['1', '=', '0', 'AND']; // "1 = 0"
            return $this;
        }

        $params = [];
        foreach (array_values($values) as $v) {
            $p = ':w' . count($this->bindings);
            $params[] = $p;
            $this->bindings[$p] = $v;
        }

        $this->wheres[] = [$column, 'IN', '(' . implode(', ', $params) . ')', 'AND'];
        return $this;
    }

    /**
     * OR column IN (...)
     */
    public function orWhereIn(string $column, array $values): self
    {
        if (empty($values)) {
            return $this;
        }

        $params = [];
        foreach (array_values($values) as $v) {
            $p = ':w' . count($this->bindings);
            $params[] = $p;
            $this->bindings[$p] = $v;
        }

        $this->wheres[] = [$column, 'IN', '(' . implode(', ', $params) . ')', 'OR'];
        return $this;
    }

    // ---- Order / Limit ----
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $dir = strtoupper($direction);
        if (!in_array($dir, ['ASC', 'DESC'], true)) $dir = 'ASC';
        $this->orderBy[] = [$column, $dir];
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    // ---- Fetch ----
    public function get(): array
    {
        $sql = $this->toSelectSql();
        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bindings as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function find(int $id, string $idColumn = 'id'): ?array
    {
        return $this->where($idColumn, '=', $id)->first();
    }

    // ---- Write ----
    public function insert(array $data): int
    {
        if (!$this->table) throw new RuntimeException("No table selected");

        $cols = array_keys($data);
        $params = [];
        $bindings = [];

        foreach ($data as $col => $val) {
            $p = ':i_' . $col;
            $params[] = $p;
            $bindings[$p] = $val;
        }

        $sql = "INSERT INTO {$this->table} (" . implode(',', $cols) . ") VALUES (" . implode(',', $params) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(array $data): int
    {
        if (!$this->table) throw new RuntimeException("No table selected");

        $sets = [];
        $bindings = [];

        foreach ($data as $col => $val) {
            $p = ':u_' . $col;
            $sets[] = "{$col} = {$p}";
            $bindings[$p] = $val;
        }

        [$whereSql, $whereBindings] = $this->compileWheres();
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . $whereSql;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($bindings, $whereBindings));

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        if (!$this->table) throw new RuntimeException("No table selected");

        [$whereSql, $whereBindings] = $this->compileWheres();
        $sql = "DELETE FROM {$this->table}" . $whereSql;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($whereBindings);

        return $stmt->rowCount();
    }

    // ---- Internals ----
    private function toSelectSql(): string
    {
        if (!$this->table) throw new RuntimeException("No table selected");

        $cols = implode(', ', $this->columns);
        $sql = "SELECT {$cols} FROM {$this->table}";

        [$whereSql, ] = $this->compileWheres();
        $sql .= $whereSql;

        if ($this->orderBy) {
            $parts = array_map(fn($o) => "{$o[0]} {$o[1]}", $this->orderBy);
            $sql .= " ORDER BY " . implode(', ', $parts);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT " . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= " OFFSET " . $this->offset;
        }

        return $sql;
    }

    private function compileWheres(): array
    {
        if (!$this->wheres) return ['', $this->bindings];

        $sql = " WHERE ";
        $first = true;

        foreach ($this->wheres as [$col, $op, $param, $bool]) {
            if (!$first) $sql .= " {$bool} ";

            // Caso especial: when we injected "1 = 0" style
            if ($col === '1' && $op === '=' && $param === '0') {
                $sql .= "1 = 0";
            } else {
                $sql .= "{$col} {$op} {$param}";
            }

            $first = false;
        }

        return [$sql, $this->bindings];
    }

    private function reset(): void
    {
        $this->table = '';
        $this->columns = ['*'];
        $this->wheres = [];
        $this->bindings = [];
        $this->limit = null;
        $this->offset = null;
        $this->orderBy = [];
    }
}