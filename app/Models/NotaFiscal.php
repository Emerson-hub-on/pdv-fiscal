class NotaFiscal extends Model
{
    protected $guarded = [];

    public function empresa() { return $this->belongsTo(Empresa::class); }
    public function cliente() { return $this->belongsTo(Cliente::class); }
    public function itens()   { return $this->hasMany(NotaFiscalItem::class); }
    public function venda()   { return $this->belongsTo(Venda::class); } // futuro
}