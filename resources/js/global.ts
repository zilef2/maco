/*
    *** MENU of global.ts ***
-- this Project

 --DATE functions
 DiferenciaMinutos
 IsDate_GOES_formatDate
 formatToVue
 formatDate
 monthName
 TransformTdate
    Date_to_html
 --MATH  functions
    formatdinero
    zilef_nuber_format
 CalcularEdad
 CalcularSexo
 CalcularAvg

 --STRING FUNCTIONS

 sinTildes
 NoUnderLines
 ReemplazarTildes
 PrimerosCaracteres
 PrimerasPalabras
 textoSinEspaciosLargos

 --ARRAY

 vectorSelect
*/

export function formatPesosCol(number:number): string {
    number = Math.round(number);
    const formattedNumber = number.toLocaleString('en-US', {
        minimumFractionDigits: 0, // Mínimo de dígitos decimales (en este caso, 0)
        maximumFractionDigits: 0 // Máximo de dígitos decimales (en este caso, 0)
    });

    // Reemplazar la coma por un punto como separador decimal
    return '$ ' + formattedNumber;
    // return '$ ' + number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, "'");
} 
import {toInteger} from "lodash";

//<editor-fold desc="This Project">
// this Project
export function LookForValueInArray(arrayOfObjects: object[], searchValue): string {
    if (arrayOfObjects === null) return null
    //ex: { title: '123', value: 1 },
    let foundObject = '';
    if (typeof searchValue == 'string') {
        for (const obj of arrayOfObjects) {
            if (obj['title'] === searchValue) {
                foundObject = obj['value'];
                break;
            }
        }
    } else {
        for (const obj of arrayOfObjects) {
            if (obj['title'] === searchValue['title']) {
                foundObject = obj['value'];
                break;
            }
        }
    }

    return foundObject;
}

// end this Project
//</editor-fold>


// DATE functions

//maded by gpt 3.5
export function DiferenciaMinutos(hora1: string, hora2: string): number {
    // Convertir las horas a objetos Date
    const date1: Date = new Date(`2000-01-01T${hora1}`);
    const date2: Date = new Date(`2000-01-01T${hora2}`);

    // Obtener la representación numérica de las horas (en milisegundos)
    const time1: number = date1.getTime();

    const time2: number = date2.getTime();

    // Obtener la diferencia en milisegundos y convertirla a minutos
    const diferenciaMs: number = (time1 - time2);
    // const diferenciaMs:number = Math.abs(time1 - time2);
    return Math.floor(diferenciaMs / (1000 * 60));
}

export function IsDate_GOES_formatDate(text: string): string {
    const [day, month, year] = text.split('/').map(Number);
    const date = new Date(year, month - 1, day);
    const Ndate = toInteger(date)
    const ANumber = !isNaN(Ndate);

    if (!(ANumber)) {
        return "No es una fecha";
        // throw new Error("El texto no es una fecha válida");
    }

    return formatToVue(date);
}

export function formatToVue(date): string {
    const day = date.getDate();
    const month = date.getMonth() + 1;
    const year = date.getFullYear();

    return `${day}/${month}/${year}`;
}

export function formatDate(date: string | Date, mode: "fecha" | "conLaHora" = "fecha"): string {
  const validDate = date instanceof Date ? new Date(date.getTime()) : new Date(date as string);

  const twoDigits = (num: number) => (num < 10 ? "0" + num : "" + num);

  const day = twoDigits(validDate.getDate());
  const month = monthName(twoDigits(validDate.getMonth() + 1));
  const year = validDate.getFullYear();
  const currentYear = new Date().getFullYear();

  const shortYear = year.toString().slice(-2);
  const showYear = year !== currentYear ? `-${shortYear}` : "";

  if (mode === "conLaHora") {
    let hours = validDate.getHours();
    const minutes = twoDigits(validDate.getMinutes());
    const ampm = hours >= 12 ? "PM" : "AM";
    hours = hours % 12 || 12;
    const time = `${hours}:${minutes} ${ampm}`;
    return `${day}-${month}${showYear} | ${time}`;
  }

  return `${day}-${month}${showYear}`;
}


export function TimeTo12Format(timeString) {
    if (timeString === null) return '';
    const [hours, minutes] = timeString.split(':');

    // Convert the time to 12-hour format and add 'am' or 'pm'
    return new Date(2023, 7, 5, parseInt(hours), parseInt(minutes)).toLocaleString('es-CO', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

export function formatTime(date): string {
    let validDate
    if (date) {
        validDate = new Date(date)
    } else {
        validDate = new Date()
    }
    // validDate = new Date(validDate.getTime() + (5 * 60 * 60 * 1000))

    const hora = validDate.getHours();
    const hourAndtime = (hora < 10 ? '0' : '') + hora + ':'
        + (validDate.getMinutes() < 10 ? '0' : '')
        + validDate.getMinutes() + ':00';

    return `${hourAndtime}`;
}


export function monthName(monthNumber) {
    if (monthNumber == 1) return 'Enero';
    if (monthNumber == 2) return 'Febrero';
    if (monthNumber == 3) return 'Marzo';
    if (monthNumber == 4) return 'Abril';
    if (monthNumber == 5) return 'Mayo';
    if (monthNumber == 6) return 'Junio';
    if (monthNumber == 7) return 'Julio';
    if (monthNumber == 8) return 'Agosto';
    if (monthNumber == 9) return 'Septiembre';
    if (monthNumber == 10) return 'Octubre';
    if (monthNumber == 11) return 'Noviembre';
    if (monthNumber == 12) return 'Diciembre';
}

export function TransformTdate(dateString) {
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = ('0' + (date.getMonth() + 1)).slice(-2);
    const day = ('0' + date.getDate()).slice(-2);
    const hours = ('0' + date.getHours()).slice(-2);
    const minutes = ('0' + date.getMinutes()).slice(-2);
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}
export function NumToHour(HourWithDecimals : number) {
    const hours = Math.floor(HourWithDecimals)
    console.log("🚀🚀NumToHour ~ hours: ", hours);
    console.log("🚀🚀NumToHour ~ HourWithDecimals: ", HourWithDecimals);
    const minutes = Math.floor((HourWithDecimals - hours) * 60)
    return `${hours} h ${minutes} mins`;
}

export function Date_to_html(date: Date): string {
    if (!(date instanceof Date) || isNaN(date.getTime())) {
        return ''
    }
    const day = date.getDate();
    const month = date.getMonth() + 1;
    const year = date.getFullYear();
    const day2: string = day < 10 ? '0' + day : day + '';

    const month2: string = month < 10 ? '0' + month : month + '';

    return `${year}-${month2}-${day2}`;
}

// fin DATE functions


// MATH  functions
export function formatdinero(amount: any): string {
    if (typeof amount === 'undefined' || amount === null || amount === '') return '$0';
    
    // Extraer solo números y signo negativo
    const strAmount = amount.toString().replace(/[^0-9\.-]/g, '');
    const num = parseFloat(strAmount);
    
    if (isNaN(num)) return '$0';

    let integerPart = Math.round(num).toString();
    const isNegative = integerPart.startsWith('-');
    if (isNegative) integerPart = integerPart.substring(1);
    
    const reversed = integerPart.split('').reverse().join('');
    let formatted = '';
    
    for (let i = 0; i < reversed.length; i++) {
        if (i > 0 && i % 3 === 0) {
            if (i % 6 === 0) {
                formatted += "'"; // Comilla para millones
            } else {
                formatted += "."; // Punto para miles
            }
        }
        formatted += reversed[i];
    }
    
    const result = '$' + formatted.split('').reverse().join('');
    return isNegative ? '-' + result : result;
}
export function dineroANumero(valor: any): number {
    const texto = (valor ?? '').toString().trim();
    if (texto === '') return 0;

    // Formato de la app: $1.500 / $1'500.000 (punto y comilla como separadores de miles).
    if (/^\$?-?\d{1,3}(?:[.'’]\d{3})+$/.test(texto)) {
        return parseInt(texto.replace(/[^0-9-]/g, ''), 10) || 0;
    }

    const num = parseFloat(texto.replace(/[^0-9.-]/g, ''));
    return isNaN(num) ? 0 : num;
}

export function CalcularAvg(TheArray, NameValue = '', isTime = false) {
    if(!TheArray) return 0
    let sum: number
    sum = 0
    if (NameValue === '') {
        TheArray.forEach((value, index, array) => {
            sum += parseFloat(value);
        })
    } else {
        if (isTime) { //time like: 14:18
            TheArray.forEach((value, index, array) => {
                console.log("🚀🚀CalcularAvg ~ value[NameValue]: ", value[NameValue]);
                let justHour:number = 0
                if(value[NameValue]){
                    justHour = parseInt(value[NameValue].split(':')[0])
                }
                sum += justHour;
                console.log("🚀🚀CalcularAvg ~ sum: ", sum);
            })
        } else {
            TheArray.forEach((value, index, array) => {
                if(value[NameValue]){
                    console.log("🚀🚀CalcularAvg ~ value[NameValue]: ", value[NameValue]);
                    // let val = value[NameValue].replace(',', '.')
                    console.log("🚀🚀CalcularAvg ~ value: ", value);
                    // sum += parseFloat(val);
                }
            })
        }
    }
    let NewSum : number = sum / TheArray.length
    let result : string
    if(isTime){
        if( NewSum > 12){
            NewSum -= 12
        }
        result = zilef_nuber_format(NewSum, 0, false);
    }else{
        
        result = zilef_nuber_format(NewSum, 1, false);
    }
    return result;
}

export function zilef_nuber_format(amount: any, decimals = 0, isPesos: boolean = false) {
    amount += '';
    amount = parseFloat(amount.replace(/[^0-9\.]/g, ''));
    decimals = decimals || 0;

    if (isNaN(amount) || amount === 0)
        return parseFloat("0").toFixed(decimals);
    amount = Number(amount.toFixed(decimals)).toString();

    const amount_parts = amount.split(' '),
        regexp = /(\d+)(\d{3})/;

    while (regexp.test(amount_parts[0]))
        amount_parts[0] = amount_parts[0].replace(regexp, '$1' + '.' + '$2');

    if (isPesos)
        return '$' + amount_parts.join(' ');
    return amount_parts.join(' ');
}

export function CalcularEdad(nacimiento) {
    if (!nacimiento) return '';
    const [anio, mes, dia] = String(nacimiento).slice(0, 10).split('-').map(Number);
    if (!anio || !mes || !dia) return '';
    const hoy = new Date();
    let edad = hoy.getFullYear() - anio;
    const mesActual = hoy.getMonth() + 1;
    if (mesActual < mes || (mesActual === mes && hoy.getDate() < dia)) {
        edad--;
    }
    return edad;
}

export function CalcularSexo(value) {
    return value == 0 ? 'Masculino' : 'Femenino'
}




//STRING FUNCTIONS
export function sinTildes(value) {
    const pattern = /[^a-zA-Z0-9\s]/g;
    const replacement = '';
    const result = value.replace(pattern, replacement);
    return result
}

export function NoUnderLines(value) {
    return value.replace(/[^a-zA-Z0-9]/g, ' ');
}


export function ReemplazarTildes(texto) {
    return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

export function PrimerosCaracteres(texto, caracteres = 15) {
    if (texto) {

        if (texto.length > caracteres + 5) {

            const primeros = texto.substring(0, caracteres);
            return primeros + '...';
        }
        return texto
    }
}

export function PrimerasPalabras(texto, palabras = 10) {
    if (texto) {
        const firstWords = texto.split(" ");
        if (firstWords.length > palabras) {
            const primeros = firstWords.slice(0, palabras).join(" ");
            return primeros + '...';
        }
        return texto
    }
}

export function textoSinEspaciosLargos(texto) {
    return texto.replace(/\s+/g, ' ');
}


//array functions
export function vectorSelect(vectorSelect, propsVector, genero = 'uno') {
    vectorSelect = propsVector.map(
        generico => (
            {label: generico.nombre, value: generico.id}
        )
    )
    vectorSelect.unshift({label: 'Seleccione ' + genero, value: 0})
    return vectorSelect;
}

/*
watch(() => form.tipoRes, (newX) => {
    data.selectedPrompID = 'Selecciona un promp'
})
*/
