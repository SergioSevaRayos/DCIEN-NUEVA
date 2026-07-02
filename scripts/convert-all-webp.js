import fs from 'fs';
import path from 'path';
import sharp from 'sharp';

async function convertDirectoryToWebP(directoryPath) {
  try {
    const files = fs.readdirSync(directoryPath);
    for (const file of files) {
      const fullPath = path.join(directoryPath, file);
      const stat = fs.statSync(fullPath);
      
      if (stat.isDirectory()) {
        await convertDirectoryToWebP(fullPath);
      } else {
        const ext = path.extname(file).toLowerCase();
        if (['.png', '.jpg', '.jpeg'].includes(ext)) {
          const outputPath = path.join(directoryPath, `${path.parse(file).name}.webp`);
          
          await sharp(fullPath)
            .webp({ quality: 80 })
            .toFile(outputPath);
          
          console.log(`Converted: ${fullPath} -> ${outputPath}`);
          // fs.unlinkSync(fullPath);
        }
      }
    }
  } catch (err) {
    console.error(`Error in ${directoryPath}:`, err);
  }
}

const baseDir = path.join(process.cwd(), 'public/images');
convertDirectoryToWebP(baseDir);
