type Props = {
    code: string;
};

export function ReferenceCode({ code }: Props) {
    return <span className="font-mono text-xs tracking-wider uppercase">{code}</span>;
}
